<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComment extends Model
{
    protected $fillable = ['ticket_id','user_id','body','internal'];
    protected function casts(): array { return ['internal'=>'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
