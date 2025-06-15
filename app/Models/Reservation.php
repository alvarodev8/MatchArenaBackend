<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'player_id',
        'pitch_id',
        'start_at',
        'status',
        'duration',
        'price',
        'payment_status',
        'payment_method',
        'stripe_payment_intent_id',
        'cancellation_reason',
        'cancellation_date'
    ];

    protected $dates = [
        'start_at',
        'cancellation_date',
        'deleted_at'
    ];

    protected $attributes = [
        'payment_status' => 'pending',
    ];

    protected $casts = [
        'payment_status' => 'string',
    ];

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function pitch()
    {
        return $this->belongsTo(Pitch::class, 'pitch_id');
    }
}
