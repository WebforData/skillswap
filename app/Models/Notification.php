<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['user_id', 'reservation_id', 'title', 'message', 'is_read'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }
}
