<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog,Ticket,Website};
use App\Services\SafeWebsiteUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

    public function dashboard(Request $request)
    {
        $websites=$this->websiteQuery($request); $tickets=$this->ticketQuery($request);
        return ['data'=>['websites'=>$websites->count(),'healthy'=>(clone $websites)->where('is_up',true)->count(),'open_tickets'=>(clone $tickets)->whereNotIn('status',['resolved','closed'])->count(),
            'recent_tickets'=>(clone $tickets)->with('website:id,name')->latest()->limit(6)->get(),'recent_websites'=>(clone $websites)->latest()->limit(5)->get(['id','name','url','status','is_up','response_ms','last_checked_at'])]];
    }
    public function websites(Request $request) { return $this->websiteQuery($request)->with('technician:id,name')->latest()->paginate(15); }
    private function websiteQuery(Request $request) { return Website::query()->when(!$request->user()->isStaff(),fn($q)=>$q->where('organisation_id',$request->user()->organisation_id))->when($request->user()->role==='technician',fn($q)=>$q->where('technician_id',$request->user()->id)); }

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
        $data=$request->validate(['status'=>'required|in:active,paused,rejected,archived','technician_id'=>'nullable|exists:users,id']);
        if(isset($data['technician_id'])) abort_unless(\App\Models\User::whereKey($data['technician_id'])->where('role','technician')->exists(),422,'Assigned user must be a technician.');
        $website->update(['status'=>$data['status'],'technician_id'=>$data['technician_id']??$website->technician_id]);
        $this->audit($request,'website.reviewed',$website);
        return ['data'=>$website->fresh()->load('technician:id,name')];
    }

    public function tickets(Request $request) { return $this->ticketQuery($request)->with(['website:id,name','reporter:id,name','assignee:id,name'])->latest()->paginate(15); }
    private function ticketQuery(Request $request) { return Ticket::query()->when(!$request->user()->isStaff(),fn($q)=>$q->where('organisation_id',$request->user()->organisation_id))->when($request->user()->role==='technician',fn($q)=>$q->where('assignee_id',$request->user()->id)); }
    public function storeTicket(Request $request)
    {
        abort_unless($request->user()->role==='client',403); $data=$request->validate(['website_id'=>'required|integer','subject'=>'required|string|max:180','description'=>'required|string|max:10000','category'=>'required|string|max:80','priority'=>'required|in:low,normal,high,urgent']);
        $website=Website::where('organisation_id',$request->user()->organisation_id)->where('status','active')->findOrFail($data['website_id']);
        $ticket=DB::transaction(function()use($data,$website,$request){$number='SC-'.now()->format('Y').'-'.str_pad((string)(Ticket::whereYear('created_at',now()->year)->lockForUpdate()->count()+1),5,'0',STR_PAD_LEFT);$hours=['low'=>72,'normal'=>24,'high'=>4,'urgent'=>1][$data['priority']];return Ticket::create($data+['number'=>$number,'website_id'=>$website->id,'organisation_id'=>$website->organisation_id,'reporter_id'=>$request->user()->id,'status'=>'open','response_due_at'=>now()->addHours($hours)]);});
        $this->audit($request,'ticket.created',$ticket); return response()->json(['data'=>$ticket],201);
    }
    public function updateTicket(Request $request,Ticket $ticket)
    {
        $this->authorizeTicket($request,$ticket); $data=$request->validate(['status'=>'sometimes|in:open,triaged,assigned,in_progress,waiting_for_client,resolved,closed','priority'=>'sometimes|in:low,normal,high,urgent','assignee_id'=>'sometimes|nullable|exists:users,id']);
        if (isset($data['status'])) {$transitions=['open'=>['triaged','assigned'],'triaged'=>['assigned','in_progress'],'assigned'=>['in_progress','waiting_for_client'],'in_progress'=>['waiting_for_client','resolved'],'waiting_for_client'=>['in_progress','resolved'],'resolved'=>['open','closed'],'closed'=>[]];$role=$request->user()->role;$allowed=$role==='admin'||($role==='client'&&$ticket->status==='resolved'&&$data['status']==='open')||($role==='technician'&&in_array($data['status'],$transitions[$ticket->status]??[],true));abort_unless($allowed,422,'Invalid status transition.');if($data['status']==='resolved')$data['resolved_at']=now();if($data['status']==='closed')$data['closed_at']=now();}
        if(array_key_exists('assignee_id',$data)){abort_unless($request->user()->role==='admin',403);if($data['assignee_id']!==null)abort_unless(\App\Models\User::whereKey($data['assignee_id'])->where('role','technician')->exists(),422,'Assigned user must be a technician.');}
        $ticket->update($data);$this->audit($request,'ticket.updated',$ticket);return ['data'=>$ticket->fresh()->load(['website:id,name','assignee:id,name'])];
    }
    public function comments(Request $request,Ticket $ticket) { $this->authorizeTicket($request,$ticket);return ['data'=>$ticket->comments()->when(!$request->user()->isStaff(),fn($q)=>$q->where('internal',false))->with('user:id,name,role')->oldest()->get()]; }
    public function storeComment(Request $request,Ticket $ticket) {$this->authorizeTicket($request,$ticket);$data=$request->validate(['body'=>'required|string|max:10000','internal'=>'sometimes|boolean']);abort_if(($data['internal']??false)&&!$request->user()->isStaff(),403);$comment=$ticket->comments()->create(['body'=>$data['body'],'internal'=>$data['internal']??false,'user_id'=>$request->user()->id]);$this->audit($request,'ticket.comment_added',$comment);return response()->json(['data'=>$comment->load('user:id,name,role')],201);}
    private function authorizeTicket(Request $request,Ticket $ticket):void {$user=$request->user();abort_unless($user->role==='admin'||($user->role==='technician'?$ticket->assignee_id===$user->id:$ticket->organisation_id===$user->organisation_id),404);abort_if($user->role==='client'&&$ticket->organisation?->status!=='active',403);}
    private function audit(Request $request,string $action,$subject):void {AuditLog::create(['actor_id'=>$request->user()->id,'organisation_id'=>$request->user()->organisation_id,'action'=>$action,'subject_type'=>class_basename($subject),'subject_id'=>$subject->id,'ip_address'=>$request->ip()]);}
}
