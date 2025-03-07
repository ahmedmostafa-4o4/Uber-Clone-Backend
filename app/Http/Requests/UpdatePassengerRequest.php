<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePassengerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'sometimes|email|unique:passengers,email|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'phone_number' => 'sometimes|string|regex:/^\+?[0-9]{10,15}$/|unique:drivers,phone_number',
            'address' => 'sometimes|string',
            'email_verified_at' => 'sometimes|nullable|date',
        ];
    }
}
