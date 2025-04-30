<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fixture extends Model
{
    use HasFactory;

    protected $fillable = ['player_id', 'pitch_id', 'date', 'opponent'];

    protected $casts = [
        'date' => 'datetime',
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
