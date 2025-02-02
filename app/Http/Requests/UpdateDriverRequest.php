<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:drivers,email|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'phone_number' => 'required|string|regex:/^\+?[0-9]{10,15}$/|unique:drivers,phone_number',
            'address' => 'required|string',
            'license_number' => 'required|string|max:50|unique:drivers,license_number',
            'driving_experience' => 'required|numeric|min:0|max:50',
            'car_model' => 'required|string|max:255',
            'rating' => 'required',
            'license_plate' => 'required|string|max:50|unique:drivers,license_plate',
            'car_color' => 'required|string|max:50',
            'manufacturing_year' => 'required|digits:4|integer|min:1900|max:' . date('Y'),
            'insurance_info' => 'required',
            'registration_info' => 'required',
        ];
    }
}
