<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    protected $fillable = ['name','slug','primary_contact','contact_email','contact_phone','contact_address','status'];
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function websites(): HasMany { return $this->hasMany(Website::class); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
}
