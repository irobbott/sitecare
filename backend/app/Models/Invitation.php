<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable=['organisation_id','email','role','token_hash','expires_at','accepted_at','invited_by'];
    protected function casts():array{return ['expires_at'=>'datetime','accepted_at'=>'datetime'];}
    public function organisation():BelongsTo{return $this->belongsTo(Organisation::class);}
}
