<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // On autorise, et on laisse le contrôleur vérifier si l'utilisateur peut modifier
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }
}
