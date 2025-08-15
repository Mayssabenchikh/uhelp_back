<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorisation simple : on laisse le contrôleur vérifier le ticket et l'utilisateur
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
