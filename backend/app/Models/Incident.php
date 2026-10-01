<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable=['website_id','type','started_at','recovered_at','status','last_error','failed_checks'];
    protected function casts():array{return ['started_at'=>'datetime','recovered_at'=>'datetime'];}
}
