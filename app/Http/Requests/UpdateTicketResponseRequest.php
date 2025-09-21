<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTicketResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();

    }

    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'max:5000',
            ],
            'attachment' => [
                'nullable',
                'file',
                'max:10240', // 10MB max
                'mimes:jpeg,jpg,png,gif,pdf,doc,docx,txt,zip,rar'
            ],
        ];
    }
}
