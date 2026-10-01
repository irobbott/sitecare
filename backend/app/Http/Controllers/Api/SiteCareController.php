<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog,BackupRecord,Incident,Invitation,MaintenanceRecord,Ticket,TicketAttachment,Website,UptimeCheck};
use App\Mail\InvitationMail;
use App\Notifications\SiteCareAlert;
use App\Services\SafeWebsiteUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SiteCareController extends Controller
{
    public function login(Request $request)
    {
        $data=$request->validate(['email'=>'required|email','password'=>'required|string']); $user=\App\Models\User::where('email',$data['email'])->first();
        if (!$user || !Hash::check($data['password'],$user->password)) return response()->json(['message'=>'The supplied credentials are incorrect.'],422);
        if ($user->organisation && $user->organisation->status!=='active') return response()->json(['message'=>'This organisation is suspended.'],403);
        $request->session()->regenerate(); return ['data'=>['user'=>$user->only('id','name','email','role','organisation_id')]];
    }
    public function me(Request $request) { return ['data'=>$request->user()->only('id','name','email','role','organisation_id')]; }
    public function logout(Request $request) { $request->session()->invalidate(); $request->session()->regenerateToken(); return response()->noContent(); }
    public function updateProfile(Request $request)
    {
        $user=$request->user();
        $data=$request->validate([
            'name'=>'sometimes|required|string|max:120',
            'email'=>'sometimes|required|email:rfc|max:255|unique:users,email,'.$user->id,
            'current_password'=>'required_with:password|current_password',
            'password'=>['sometimes','required','confirmed',Password::min(12)],
        ]);
        if(isset($data['password'])){
            Auth::logoutOtherDevices($data['current_password']);
            unset($data['current_password']);
        } else unset($data['current_password']);
        $user->update($data);
        $this->audit($request,'profile.updated',$user);
        return ['data'=>$user->fresh()->only('id','name','email','role','organisation_id')];
    }
    public function notifications(Request $request)
    {
        return ['data'=>$request->user()->notifications()->latest()->limit(50)->get()->map(fn($notice)=>[
            'id'=>$notice->id,'data'=>$notice->data,'read_at'=>$notice->read_at,'created_at'=>$notice->created_at,
        ])];
    }
    public function readNotification(Request $request,string $notification)
    {
        $notice=$request->user()->notifications()->whereKey($notification)->firstOrFail();
        $notice->markAsRead();
        return ['data'=>['id'=>$notice->id,'read_at'=>$notice->read_at]];
    }
    public function updateNotificationPreferences(Request $request)
    {
        $data=$request->validate(['in_app'=>'required|boolean','email'=>'required|boolean']);
        $request->user()->update(['notification_preferences'=>$data]);
        return ['data'=>$request->user()->fresh()->notification_preferences];
    }

    public function invitations(Request $request)
    {
        abort_unless($request->user()->role==='admin',403);
        return ['data'=>Invitation::with('organisation:id,name')->whereNull('accepted_at')->where('expires_at','>',now())->latest()->paginate(15)];
    }
    public function storeInvitation(Request $request)
    {
        abort_unless($request->user()->role==='admin',403);
        $data=$request->validate(['email'=>'required|email:rfc|max:255','role'=>'required|in:admin,technician,client','organisation_id'=>'nullable|required_if:role,client|exists:organisations,id']);
        $email=strtolower($data['email']);
        abort_if(\App\Models\User::where('email',$email)->exists(),422,'A user already has this email address.');
        if($data['role']==='client')abort_unless(\App\Models\Organisation::whereKey($data['organisation_id'])->where('status','active')->exists(),422,'The client organisation is suspended.');
        $plain=Str::random(64);$expires=now()->addDays(7);
        $invitation=Invitation::create(['email'=>$email,'role'=>$data['role'],'organisation_id'=>$data['role']==='client'?$data['organisation_id']:null,'token_hash'=>hash('sha256',$plain),'expires_at'=>$expires,'invited_by'=>$request->user()->id]);
        $url=rtrim(config('app.frontend_url',env('FRONTEND_URL','http://localhost:5173')),'/').'/accept-invitation?token='.urlencode($plain).'&email='.urlencode($email);
        try{Mail::to($email)->queue(new InvitationMail($url,$data['role']));}catch(\Throwable $error){report($error);}
        $this->audit($request,'invitation.sent',$invitation);
        return response()->json(['data'=>['id'=>$invitation->id,'email'=>$email,'role'=>$invitation->role,'expires_at'=>$expires,'invitation_url'=>$url]],201);
    }
    public function acceptInvitation(Request $request)
    {
        $data=$request->validate(['token'=>'required|string|min:40|max:100','email'=>'required|email','name'=>'required|string|max:120','password'=>['required','confirmed',Password::min(12)]]);
        $invitation=Invitation::where('email',strtolower($data['email']))->where('token_hash',hash('sha256',$data['token']))->whereNull('accepted_at')->where('expires_at','>',now())->firstOrFail();
        if($invitation->role==='client')abort_unless($invitation->organisation?->status==='active',403,'This client organisation is suspended.');
        $user=DB::transaction(function()use($invitation,$data){$locked=Invitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();abort_if($locked->accepted_at||$locked->expires_at->isPast(),422,'This invitation has expired or was already used.');$user=\App\Models\User::create(['name'=>$data['name'],'email'=>$locked->email,'password'=>$data['password'],'role'=>$locked->role,'organisation_id'=>$locked->organisation_id]);$locked->update(['accepted_at'=>now()]);return $user;});
        return response()->json(['data'=>['user'=>$user->only('id','name','email','role','organisation_id')]],201);
    }
    public function revokeInvitation(Request $request,Invitation $invitation)
    {
        abort_unless($request->user()->role==='admin',403);abort_if($invitation->accepted_at,409,'Accepted invitations cannot be revoked.');
        $invitation->delete();$this->audit($request,'invitation.revoked',$invitation);return response()->noContent();
    }

    public function dashboard(Request $request)
    {
        $websites=$this->websiteQuery($request); $tickets=$this->ticketQuery($request);$websiteIds=(clone $websites)->select('id');$checks=UptimeCheck::whereIn('website_id',$websiteIds)->where('checked_at','>=',now()->subDays(30));$checksTotal=(clone $checks)->count();$checksAvailable=(clone $checks)->where('available',true)->count();$daily=UptimeCheck::whereIn('website_id',(clone $websites)->select('id'))->where('checked_at','>=',now()->subDays(29))->selectRaw('DATE(checked_at) as day, COUNT(*) as checks, SUM(available) as available')->groupByRaw('DATE(checked_at)')->orderBy('day')->get();
        return ['data'=>['websites'=>$websites->count(),'healthy'=>(clone $websites)->where('is_up',true)->count(),'open_tickets'=>(clone $tickets)->whereNotIn('status',['resolved','closed'])->count(),
            'checks_total'=>$checksTotal,'uptime_percent'=>$checksTotal?round($checksAvailable*100/$checksTotal,2):null,'average_response_ms'=>(clone $checks)->avg('response_ms'),'daily_uptime'=>$daily->map(fn($day)=>['date'=>$day->day,'checks'=>(int)$day->checks,'uptime_percent'=>$day->checks?round((int)$day->available*100/(int)$day->checks,2):null])->values(),
            'recent_tickets'=>(clone $tickets)->with('website:id,name')->latest()->limit(6)->get(),'recent_websites'=>(clone $websites)->latest()->limit(5)->get(['id','name','url','status','is_up','response_ms','last_checked_at'])]];
    }
    public function websites(Request $request) { return $this->websiteQuery($request)->with('technician:id,name')->latest()->paginate(15); }
    public function websiteHealth(Request $request,Website $website)
    {
        $this->authorizeWebsite($request,$website);
        $latestSsl=$website->sslChecks()->latest('checked_at')->first();
        return ['data'=>[
            'website'=>$website->only('id','name','url','status','is_up','response_ms','last_checked_at'),
            'ssl'=>$latestSsl,
            'uptime_checks'=>$website->checks()->latest('checked_at')->limit(30)->get(),
            'incidents'=>$website->incidents()->latest('started_at')->limit(10)->get(),
        ]];
    }
    private function websiteQuery(Request $request) { $user=$request->user();return Website::query()->when(!$user->isStaff(),fn($q)=>$q->where('organisation_id',$user->organisation_id))->when($user->role==='client'&&$user->organisation?->status!=='active',fn($q)=>$q->whereRaw('1 = 0'))->when($user->role==='technician',fn($q)=>$q->where('technician_id',$user->id)); }

    public function storeWebsite(Request $request,SafeWebsiteUrl $safe)
    {
        abort_unless($request->user()->role==='client' && $request->user()->organisation?->status==='active',403);
        $data=$request->validate(['name'=>'required|string|max:120','url'=>'required|url:http,https|max:2048','staging_url'=>'nullable|url:http,https|max:2048','description'=>'nullable|string|max:2000','technology_notes'=>'nullable|string|max:2000','hosting_provider'=>'nullable|string|max:120']);
        $safe->assertPublic($data['url']); if (!empty($data['staging_url'])) $safe->assertPublic($data['staging_url']);
        $website=Website::create($data+['organisation_id'=>$request->user()->organisation_id,'status'=>'pending','monitor_interval'=>15]);
        $this->audit($request,'website.submitted',$website); return response()->json(['data'=>$website],201);
    }
    public function reviewWebsite(Request $request,Website $website)
    {
        abort_unless($request->user()->role==='admin',403);
        $data=$request->validate(['status'=>'required|in:active,paused,rejected,archived','technician_id'=>'nullable|exists:users,id','internal_verification_notes'=>'nullable|string|max:3000','rejection_reason'=>'required_if:status,rejected|nullable|string|max:2000']);
        if(isset($data['technician_id'])) abort_unless(\App\Models\User::whereKey($data['technician_id'])->where('role','technician')->exists(),422,'Assigned user must be a technician.');
        $website->update(['status'=>$data['status'],'technician_id'=>$data['technician_id']??$website->technician_id,'internal_verification_notes'=>$data['internal_verification_notes']??$website->internal_verification_notes,'rejection_reason'=>$data['status']==='rejected'?$data['rejection_reason']:null]);
        if(in_array($website->status,['active','rejected'],true)){
            $title=$website->status==='active'?'Website approved':'Website submission needs changes';
            $message=$website->status==='active'?$website->name.' is approved and monitoring can begin.':$website->name.' was not approved: '.$website->rejection_reason;
            \App\Models\User::where('organisation_id',$website->organisation_id)->where('role','client')->get()->each(fn($member)=>$this->notify($member,$title,$message,'/'));
        }
        $this->audit($request,'website.reviewed',$website);
        return ['data'=>$website->fresh()->makeVisible('internal_verification_notes')->load('technician:id,name')];
    }

    public function backups(Request $request,Website $website)
    {
        $this->authorizeWebsite($request,$website);
        $records=$website->backupRecords()->latest('completed_at');
        if($request->user()->role==='client')$records->select(['id','website_id','type','status','completed_at','size_bytes','verified']);
        return ['data'=>$records->paginate(20)];
    }
    public function storeBackup(Request $request,Website $website)
    {
        $this->authorizeWebsite($request,$website);abort_unless($request->user()->isStaff(),403);
        $data=$request->validate(['type'=>'required|in:files,database,full_site,incremental','status'=>'required|in:started,completed,failed,verified','completed_at'=>'required|date','destination'=>'nullable|string|max:255','size_bytes'=>'nullable|integer|min:0|max:1099511627776','verified'=>'sometimes|boolean','notes'=>'nullable|string|max:3000']);
        $record=$website->backupRecords()->create($data+['recorded_by'=>$request->user()->id]);$this->audit($request,'backup.recorded',$record);
        return response()->json(['data'=>$record],201);
    }
    public function rotateBackupWebhookSecret(Request $request,Website $website)
    {
        abort_unless($request->user()->role==='admin',403);$secret=Str::random(64);$website->update(['webhook_secret'=>$secret]);$this->audit($request,'backup_webhook.secret_rotated',$website);
        return ['data'=>['website_id'=>$website->id,'secret'=>$secret,'signature'=>'timestamp + newline + event-id + newline + raw request body']];
    }
    public function backupWebhook(Request $request,Website $website)
    {
        abort_unless($website->status==='active'&&$website->organisation?->status==='active',404);
        $timestamp=(string)$request->header('X-SiteCare-Timestamp','');$eventId=(string)$request->header('X-SiteCare-Event-Id','');$signature=(string)$request->header('X-SiteCare-Signature','');
        abort_unless(ctype_digit($timestamp)&&abs(now()->timestamp-(int)$timestamp)<=300,401,'Invalid or expired signature timestamp.');
        abort_unless((bool)preg_match('/\A[A-Za-z0-9._:-]{1,120}\z/',$eventId),422,'A valid unique event id is required.');
        abort_unless($website->webhook_secret,401,'Webhook access is not configured.');
        $expected=hash_hmac('sha256',$timestamp."\n".$eventId."\n".$request->getContent(),$website->webhook_secret);
        abort_unless(hash_equals($expected,$signature),401,'Invalid webhook signature.');
        $data=$request->validate(['type'=>'required|in:files,database,full_site,incremental','status'=>'required|in:completed,failed','completed_at'=>'required|date','destination'=>'nullable|string|max:255','size_bytes'=>'nullable|integer|min:0|max:1099511627776','verified'=>'sometimes|boolean','notes'=>'nullable|string|max:3000']);
        $record=DB::transaction(function()use($website,$eventId,$data,$request){$inserted=DB::table('backup_webhook_events')->insertOrIgnore(['website_id'=>$website->id,'event_id'=>$eventId,'received_at'=>now()]);abort_if(!$inserted,409,'This event has already been processed.');$backup=$website->backupRecords()->create($data+['recorded_by'=>null]);AuditLog::create(['organisation_id'=>$website->organisation_id,'action'=>'backup.webhook_received','subject_type'=>'BackupRecord','subject_id'=>$backup->id,'metadata'=>['event_id'=>$eventId,'status'=>$backup->status],'ip_address'=>$request->ip()]);return $backup;});
        return response()->json(['data'=>$record],201);
    }
    public function maintenance(Request $request,Website $website)
    {
        $this->authorizeWebsite($request,$website);$records=$website->maintenanceRecords()->with('technician:id,name')->latest('completed_at');
        if($request->user()->role==='client')$records->where('client_visible',true);
        return ['data'=>$records->paginate(20)];
    }
    public function storeMaintenance(Request $request,Website $website)
    {
        $this->authorizeWebsite($request,$website);abort_unless($request->user()->isStaff(),403);
        $data=$request->validate(['ticket_id'=>'nullable|exists:tickets,id','work_type'=>'required|in:content_update,dependency_update,bug_fix,security_patch,backup,performance,deployment,dns_domain,ssl,other','summary'=>'required|string|max:180','notes'=>'nullable|string|max:5000','completed_at'=>'nullable|date','client_visible'=>'sometimes|boolean']);
        if(!empty($data['ticket_id']))abort_unless(Ticket::whereKey($data['ticket_id'])->where('website_id',$website->id)->exists(),422,'Related ticket must belong to this website.');
        $record=$website->maintenanceRecords()->create($data+['technician_id'=>$request->user()->id]);$this->audit($request,'maintenance.recorded',$record);
        return response()->json(['data'=>$record->load('technician:id,name')],201);
    }
    public function incidents(Request $request)
    {
        return Incident::whereIn('website_id',$this->websiteQuery($request)->select('id'))->with('website:id,name')->latest('started_at')->paginate(20);
    }
    public function acknowledgeIncident(Request $request,Incident $incident)
    {
        $website=Website::findOrFail($incident->website_id);$this->authorizeWebsite($request,$website);abort_unless($request->user()->role==='admin'||$request->user()->role==='technician',403);
        $data=$request->validate(['resolution_note'=>'nullable|string|max:2000']);$incident->update(['acknowledged_by'=>$request->user()->id,'resolution_note'=>$data['resolution_note']??$incident->resolution_note]);$this->audit($request,'incident.acknowledged',$incident);
        return ['data'=>$incident->fresh()->load('website:id,name')];
    }
    private function authorizeWebsite(Request $request,Website $website):void
    {
        $user=$request->user();$allowed=$user->role==='admin'||($user->role==='technician'&&$website->technician_id===$user->id)||($user->role==='client'&&$website->organisation_id===$user->organisation_id&&$user->organisation?->status==='active');
        abort_unless($allowed,404);
    }

    public function tickets(Request $request) { return $this->ticketQuery($request)->with(['website:id,name','reporter:id,name','assignee:id,name'])->latest()->paginate(15); }
    private function ticketQuery(Request $request) { $user=$request->user();return Ticket::query()->when(!$user->isStaff(),fn($q)=>$q->where('organisation_id',$user->organisation_id))->when($user->role==='client'&&$user->organisation?->status!=='active',fn($q)=>$q->whereRaw('1 = 0'))->when($user->role==='technician',fn($q)=>$q->where('assignee_id',$user->id)); }
    public function storeTicket(Request $request)
    {
        abort_unless($request->user()->role==='client'&&$request->user()->organisation?->status==='active',403); $data=$request->validate(['website_id'=>'required|integer','subject'=>'required|string|max:180','description'=>'required|string|max:10000','category'=>'required|string|max:80','priority'=>'required|in:low,normal,high,urgent']);
        $website=Website::where('organisation_id',$request->user()->organisation_id)->where('status','active')->findOrFail($data['website_id']);
        $ticket=DB::transaction(function()use($data,$website,$request){$number='SC-'.now()->format('Y').'-'.str_pad((string)(Ticket::whereYear('created_at',now()->year)->lockForUpdate()->count()+1),5,'0',STR_PAD_LEFT);$hours=['low'=>72,'normal'=>24,'high'=>4,'urgent'=>1][$data['priority']];$ticket=Ticket::create($data+['number'=>$number,'website_id'=>$website->id,'organisation_id'=>$website->organisation_id,'reporter_id'=>$request->user()->id,'status'=>'open','response_due_at'=>now()->addHours($hours)]);$ticket->events()->create(['actor_id'=>$request->user()->id,'event_type'=>'created','after_data'=>['status'=>'open','priority'=>$ticket->priority]]);return $ticket;});
        $this->audit($request,'ticket.created',$ticket);
        \App\Models\User::where('role','admin')->get()->each(fn($admin)=>$this->notify($admin,'New support ticket',$ticket->number.' · '.$ticket->subject,'/'));
        return response()->json(['data'=>$ticket],201);
    }
    public function updateTicket(Request $request,Ticket $ticket)
    {
        $this->authorizeTicket($request,$ticket); $data=$request->validate(['status'=>'sometimes|in:open,triaged,assigned,in_progress,waiting_for_client,resolved,closed','priority'=>'sometimes|in:low,normal,high,urgent','assignee_id'=>'sometimes|nullable|exists:users,id']);
        if (isset($data['status'])) {$transitions=['open'=>['triaged','assigned'],'triaged'=>['assigned','in_progress'],'assigned'=>['in_progress','waiting_for_client'],'in_progress'=>['waiting_for_client','resolved'],'waiting_for_client'=>['in_progress','resolved'],'resolved'=>['open','closed'],'closed'=>[]];$role=$request->user()->role;$allowed=$role==='admin'||($role==='client'&&$ticket->status==='resolved'&&$data['status']==='open')||($role==='technician'&&in_array($data['status'],$transitions[$ticket->status]??[],true));abort_unless($allowed,422,'Invalid status transition.');if($data['status']==='resolved')$data['resolved_at']=now();if($data['status']==='closed')$data['closed_at']=now();}
        if(array_key_exists('assignee_id',$data)){abort_unless($request->user()->role==='admin',403);if($data['assignee_id']!==null)abort_unless(\App\Models\User::whereKey($data['assignee_id'])->where('role','technician')->exists(),422,'Assigned user must be a technician.');}
        $before=$ticket->only(['status','assignee_id']);
        DB::transaction(function()use($request,$ticket,$data){$fields=['status','assignee_id','priority'];$before=$ticket->only($fields);$ticket->update($data);$after=$ticket->fresh()->only($fields);foreach($fields as $field){if(($before[$field]??null)!==($after[$field]??null))$ticket->events()->create(['actor_id'=>$request->user()->id,'event_type'=>match($field){'status'=>'status_changed','assignee_id'=>'assignment_changed',default=>'priority_changed'},'before_data'=>[$field=>$before[$field]??null],'after_data'=>[$field=>$after[$field]??null]]);}});
        if(isset($data['assignee_id'])&&$data['assignee_id']&&$data['assignee_id']!==$before['assignee_id'])$this->notify(\App\Models\User::find($data['assignee_id']),'Ticket assigned to you',$ticket->number.' · '.$ticket->subject,'/');
        if(isset($data['status'])&&$data['status']!==$before['status'])\App\Models\User::where('organisation_id',$ticket->organisation_id)->where('role','client')->get()->each(fn($member)=>$this->notify($member,'Ticket status updated',$ticket->number.' is now '.str_replace('_',' ',$data['status']).'.','/'));
        $this->audit($request,'ticket.updated',$ticket);return ['data'=>$ticket->fresh()->load(['website:id,name','assignee:id,name'])];
    }
    public function comments(Request $request,Ticket $ticket) { $this->authorizeTicket($request,$ticket);return ['data'=>$ticket->comments()->when(!$request->user()->isStaff(),fn($q)=>$q->where('internal',false))->with('user:id,name,role')->oldest()->get()]; }
    public function ticketHistory(Request $request,Ticket $ticket) { $this->authorizeTicket($request,$ticket);return ['data'=>$ticket->events()->with('actor:id,name,role')->oldest()->get()]; }
    public function attachments(Request $request,Ticket $ticket)
    {
        $this->authorizeTicket($request,$ticket);
        return ['data'=>$ticket->attachments()->with('uploader:id,name')->oldest()->get()->map(fn(TicketAttachment $attachment)=>$this->attachmentResource($attachment))];
    }
    public function storeAttachment(Request $request,Ticket $ticket)
    {
        $this->authorizeTicket($request,$ticket);
        $data=$request->validate(['file'=>'required|file|max:5120|mimes:png,jpg,jpeg,webp,pdf,txt,zip']);
        $file=$data['file'];
        $path=$file->store('tickets/'.$ticket->id,'local');
        abort_unless($path,500,'The attachment could not be stored.');
        $attachment=$ticket->attachments()->create([
            'uploaded_by'=>$request->user()->id,
            'disk'=>'local',
            'path'=>$path,
            'original_name'=>mb_substr(basename($file->getClientOriginalName()),0,255),
            'mime_type'=>$file->getMimeType() ?: 'application/octet-stream',
            'size_bytes'=>$file->getSize(),
        ]);
        $this->audit($request,'ticket.attachment_added',$attachment);
        return response()->json(['data'=>$this->attachmentResource($attachment->load('uploader:id,name'))],201);
    }
    public function downloadAttachment(Request $request,TicketAttachment $attachment)
    {
        $ticket=$attachment->ticket()->firstOrFail();
        $this->authorizeTicket($request,$ticket);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path),404);
        return Storage::disk($attachment->disk)->download($attachment->path,$attachment->original_name,[
            'Content-Type'=>'application/octet-stream',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
    private function attachmentResource(TicketAttachment $attachment):array
    {
        return $attachment->only('id','ticket_id','original_name','mime_type','size_bytes','created_at')+[
            'uploader'=>$attachment->uploader?->only('id','name'),
            'download_url'=>'/api/v1/attachments/'.$attachment->id,
        ];
    }
    public function storeComment(Request $request,Ticket $ticket) {$this->authorizeTicket($request,$ticket);$data=$request->validate(['body'=>'required|string|max:10000','internal'=>'sometimes|boolean']);abort_if(($data['internal']??false)&&!$request->user()->isStaff(),403);$comment=$ticket->comments()->create(['body'=>$data['body'],'internal'=>$data['internal']??false,'user_id'=>$request->user()->id]);if(!($data['internal']??false)){if($request->user()->role==='client'){if($ticket->assignee)$this->notify($ticket->assignee,'New client reply',$ticket->number.' received a reply.','/');}else \App\Models\User::where('organisation_id',$ticket->organisation_id)->where('role','client')->get()->each(fn($member)=>$this->notify($member,'New support reply',$ticket->number.' has a new update.','/'));}$this->audit($request,'ticket.comment_added',$comment);return response()->json(['data'=>$comment->load('user:id,name,role')],201);}
    private function authorizeTicket(Request $request,Ticket $ticket):void {$user=$request->user();abort_unless($user->role==='admin'||($user->role==='technician'?$ticket->assignee_id===$user->id:$ticket->organisation_id===$user->organisation_id),404);abort_if($user->role==='client'&&$ticket->organisation?->status!=='active',403);}
    private function audit(Request $request,string $action,$subject):void {AuditLog::create(['actor_id'=>$request->user()->id,'organisation_id'=>$request->user()->organisation_id,'action'=>$action,'subject_type'=>class_basename($subject),'subject_id'=>$subject->id,'ip_address'=>$request->ip()]);}
    private function notify(\App\Models\User $user,string $title,string $message,string $url):void
    {
        if($user->id!==request()->user()?->id)$user->notify(new SiteCareAlert($title,$message,$url));
    }
}
