<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;


class UpdateTicketRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        return [
            'titre' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'statut' => ['sometimes','required','string', Rule::in(['open','in_progress','resolved','closed'])],
            'client_id' => [
                'sometimes','required','integer',
                Rule::exists('users','id')->where(fn($query) => $query->where('role','client'))
            ],
            'agentassigne_id' => [
                'sometimes','nullable','integer',
                Rule::exists('users','id')->where(fn($query) => $query->where('role','agent'))
            ],
            'priorite' => ['sometimes','nullable','string', Rule::in(['low','medium','high'])],
            'category' => ['sometimes','nullable','string', Rule::in(['technical','billing','feature-request','bug-report','other'])],
        ];
    }
}
