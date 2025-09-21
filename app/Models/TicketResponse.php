<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 
        'user_id', 
        'message', 
        'attachment_path', 
        'attachment_name', 
        'attachment_type', 
        'attachment_size'
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if response has an attachment
     */
    public function hasAttachment(): bool
    {
        return !empty($this->attachment_path);
    }

    /**
     * Get attachment URL
     */
    public function getAttachmentUrl(): ?string
    {
        if (!$this->hasAttachment()) {
            return null;
        }
        
        return asset('storage/' . $this->attachment_path);
    }

    /**
     * Get human readable file size
     */
    public function getFormattedFileSize(): ?string
    {
        if (!$this->attachment_size) {
            return null;
        }

        $size = $this->attachment_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
}
