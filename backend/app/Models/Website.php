<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Website extends Model
{
    protected $fillable = ['organisation_id','technician_id','name','url','staging_url','description','technology_notes','hosting_provider','primary_contact','contact_email','preferred_maintenance_window','status','monitor_interval','backup_frequency_hours','backup_overdue_notified_at','webhook_secret','internal_verification_notes','rejection_reason','last_checked_at','is_up','response_ms'];
    protected $hidden = ['webhook_secret','internal_verification_notes'];
    protected function casts(): array { return ['last_checked_at'=>'datetime','backup_overdue_notified_at'=>'datetime','is_up'=>'boolean','webhook_secret'=>'encrypted']; }
    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
    public function technician(): BelongsTo { return $this->belongsTo(User::class,'technician_id'); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
    public function checks(): HasMany { return $this->hasMany(UptimeCheck::class); }
    public function sslChecks(): HasMany { return $this->hasMany(SslCheck::class); }
    public function backupRecords(): HasMany { return $this->hasMany(BackupRecord::class); }
    public function latestBackupRecord(): HasOne { return $this->hasOne(BackupRecord::class)->latestOfMany('completed_at'); }
    public function maintenanceRecords(): HasMany { return $this->hasMany(MaintenanceRecord::class); }
    public function incidents(): HasMany { return $this->hasMany(Incident::class); }
}
