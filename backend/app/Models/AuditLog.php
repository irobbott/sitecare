<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable=['actor_id','organisation_id','action','subject_type','subject_id','metadata','ip_address'];
    protected function casts(): array { return ['metadata'=>'array']; }
}
