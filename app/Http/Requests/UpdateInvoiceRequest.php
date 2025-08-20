<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
    $invoiceId = Route::current()->parameter('invoice')?->id;

        return [
            'invoice_number' => 'required|string|unique:invoices,invoice_number,' . $invoiceId,
            'user_id' => 'required|exists:users,id',
            'amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:draft,pending,paid,cancelled',
            'items' => 'nullable|array',
            'items.*.description' => 'required_with:items|string',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ];
    }
}