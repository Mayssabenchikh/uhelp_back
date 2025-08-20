<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

   protected $fillable = [
    'invoice_number','user_id','admin_id','amount','status',
    'due_date','meta','paid_at','payment_id','provider_payment_id'
];

protected $casts = [
    'meta' => 'array',
    'paid_at' => 'datetime',
    'due_date' => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];

// relations (ajoute)
public function payment()
{
    return $this->belongsTo(\App\Models\Payment::class, 'payment_id');
}
    public function client()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
