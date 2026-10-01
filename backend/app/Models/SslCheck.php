<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCheck extends Model
{
    protected $fillable = ['website_id','checked_at','monitored','subject','issuer','valid_from','expires_at','days_remaining','certificate_valid','hostname_matches','error_message'];

    protected function casts(): array
    {
        return ['checked_at'=>'datetime','valid_from'=>'datetime','expires_at'=>'datetime','monitored'=>'boolean','certificate_valid'=>'boolean','hostname_matches'=>'boolean'];
    }

    public function website(): BelongsTo { return $this->belongsTo(Website::class); }
}
