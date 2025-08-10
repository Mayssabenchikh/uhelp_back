<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
        'statut' => ['sometimes','required','string', Rule::in(['open','in_progress','closed'])],
            'client_id' => [
                'required','integer',
                Rule::exists('users','id')->where(fn($query) => $query->where('role','client'))
            ],
            'agentassigne_id' => [
                'nullable','integer',
                Rule::exists('users','id')->where(fn($query) => $query->where('role','agent'))
            ],
            'priorite' => ['nullable','string', Rule::in(['low','medium','high'])],
        ];
    }
}
