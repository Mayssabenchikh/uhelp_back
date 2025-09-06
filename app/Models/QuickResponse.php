<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickResponse extends Model
{
    protected $fillable = [
        'title',
        'content',
        'language',
        'category',
        'is_active',
        'user_id'
    ];
}
