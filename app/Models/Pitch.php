<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pitch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'location', 'establishment_id'];

    public function establishment()
    {
        return $this->belongsTo(User::class, 'establishment_id');
    }
}
