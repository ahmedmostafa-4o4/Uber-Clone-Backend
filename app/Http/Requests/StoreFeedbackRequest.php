<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Update with authorization logic if needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ride_id' => 'required|exists:rides,id',
            'passenger_id' => 'required|exists:passengers,id',
            'driver_id' => 'required|exists:drivers,id',
            'passenger_rating' => 'numeric|between:0,5',
            'driver_rating' => 'numeric|between:0,5',
            'passenger_comments' => 'nullable',
            'driver_comments' => 'nullable'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'ride_id.required' => 'The ride ID is required.',
            'ride_id.exists' => 'The selected ride does not exist.',
            'passenger_id.required' => 'The passenger ID is required.',
            'passenger_id.exists' => 'The selected passenger does not exist.',
            'driver_id.required' => 'The driver ID is required.',
            'driver_id.exists' => 'The selected driver does not exist.',
            'passenger_rating.required' => 'Passenger rating is required.',
            'passenger_rating.between' => 'Passenger rating must be between 0 and 5.',
            'driver_rating.required' => 'Driver rating is required.',
            'driver_rating.between' => 'Driver rating must be between 0 and 5.',
        ];
    }
}
