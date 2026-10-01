<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\QueuedPasswordReset;

#[Fillable(['name', 'email', 'password', 'organisation_id', 'role', 'is_demo', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function organisation(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Organisation::class); }
    public function isStaff(): bool { return in_array($this->role, ['admin', 'technician'], true); }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedPasswordReset($token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }
}
