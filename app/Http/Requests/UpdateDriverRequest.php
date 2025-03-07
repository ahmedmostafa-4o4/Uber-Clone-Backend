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
            'email' => 'sometimes|email|unique:drivers,email,' . $this->route('driver')->id . '|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'phone_number' => 'sometimes|string|regex:/^\+?[0-9]{10,15}$/|unique:drivers,phone_number' . $this->route('driver')->id,
            'address' => 'sometimes|string',
            'license_number' => 'sometimes|string|max:50|unique:drivers,license_number',
            'driving_experience' => 'sometimes|numeric|min:0|max:50',
            'car_model' => 'sometimes|string|max:255',
            'license_plate' => 'sometimes|string|max:50|unique:drivers,license_plate',
            'car_color' => 'sometimes|string|max:50',
            'manufacturing_year' => 'sometimes|date_format:Y|min:1900|max:' . date('Y'),
            'license_image' => 'nullable|array',
            'license_image.front' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'license_image.back' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'driving_license_image' => 'nullable|array',
            'driving_license_image.front' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'driving_license_image.back' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'id_card_image' => 'nullable',
            'id_card_image.front' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'id_card_image.back' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_verified' => 'sometimes|in:true,false,1,0',
        ];
    }
}
