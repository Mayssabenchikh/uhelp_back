<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingFaq extends Model
{
    use HasFactory;

    protected $table = 'pending_faqs';

    protected $fillable = [
        'question',
        'answer',
        'language',
        'category',
        'user_id',
        'status',
        'raw_model_output'
    ];

    // Scopes
    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
