<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UptimeCheck extends Model
{
    protected $fillable = ['website_id','checked_url','checked_at','status_code','available','response_ms','error_type','message'];
    protected function casts(): array { return ['checked_at'=>'datetime','available'=>'boolean']; }
}
