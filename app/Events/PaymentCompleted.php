<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

class PaymentCompleted
{
    use SerializesModels;

    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }
}
