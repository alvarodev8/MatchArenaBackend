<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pitch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'establishment_id',
        'name',
        'location',
        'price',
        'description',
    ];

    protected $dates = ['deleted_at'];

    public function establishment()
    {
        return $this->belongsTo(User::class, 'establishment_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'pitch_id');
    }
}
