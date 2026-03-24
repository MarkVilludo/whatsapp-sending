<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneNumber extends Model
{
    protected $fillable = [
        'number',
        'description',
        'provider_id',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'from_number_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'to_number_id');
    }
}
