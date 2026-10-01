<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    protected $fillable=['website_id','ticket_id','technician_id','work_type','summary','notes','completed_at','client_visible'];
    protected function casts():array{return ['completed_at'=>'datetime','client_visible'=>'boolean'];}
    public function website():BelongsTo{return $this->belongsTo(Website::class);}
    public function technician():BelongsTo{return $this->belongsTo(User::class,'technician_id');}
    public function ticket():BelongsTo{return $this->belongsTo(Ticket::class);}
}
