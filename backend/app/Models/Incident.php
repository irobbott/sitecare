<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    protected $fillable=['website_id','type','started_at','recovered_at','status','last_error','failed_checks','acknowledged_by','resolution_note'];
    protected function casts():array{return ['started_at'=>'datetime','recovered_at'=>'datetime'];}
    public function website():BelongsTo{return $this->belongsTo(Website::class);}
}
