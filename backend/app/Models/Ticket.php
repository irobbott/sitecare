<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = ['number','organisation_id','website_id','reporter_id','assignee_id','subject','description','category','priority','status','response_due_at','resolution_due_at','waiting_since','client_wait_seconds','first_response_at','resolved_at','closed_at'];
    protected function casts(): array { return ['response_due_at'=>'datetime','resolution_due_at'=>'datetime','waiting_since'=>'datetime','client_wait_seconds'=>'integer','first_response_at'=>'datetime','resolved_at'=>'datetime','closed_at'=>'datetime']; }
    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
    public function website(): BelongsTo { return $this->belongsTo(Website::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class,'reporter_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class,'assignee_id'); }
    public function comments(): HasMany { return $this->hasMany(TicketComment::class); }
    public function events(): HasMany { return $this->hasMany(TicketEvent::class); }
    public function attachments(): HasMany { return $this->hasMany(TicketAttachment::class); }
}
