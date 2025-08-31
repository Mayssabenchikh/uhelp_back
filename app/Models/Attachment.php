<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachable_id','attachable_type','user_id','disk','path','filename','mime','size'
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // helper pour URL publique (S3 ou local public disk)
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
