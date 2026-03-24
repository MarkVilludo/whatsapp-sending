<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'from_number_id',
        'to_number_id',
        'provider_id',
        'direction',
        'message_text',
        'status',
        'sent_at',
        'received_at',
        'provider_message_id',
        'logs'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function fromNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'from_number_id');
    }

    public function toNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'to_number_id');
    }
}
