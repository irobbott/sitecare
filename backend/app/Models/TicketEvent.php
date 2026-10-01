<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvent extends Model
{
    protected $fillable=['ticket_id','actor_id','event_type','before_data','after_data'];
    protected function casts():array{return ['before_data'=>'array','after_data'=>'array'];}
    public function actor():BelongsTo{return $this->belongsTo(User::class,'actor_id');}
}
