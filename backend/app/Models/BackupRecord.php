<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRecord extends Model
{
    protected $fillable=['website_id','recorded_by','type','status','completed_at','destination','size_bytes','verified','notes'];
    protected function casts():array{return ['completed_at'=>'datetime','verified'=>'boolean'];}
    public function website():BelongsTo{return $this->belongsTo(Website::class);}
    public function recorder():BelongsTo{return $this->belongsTo(User::class,'recorded_by');}
}
