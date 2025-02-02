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
            'passenger_rating' => 'required|numeric|between:0,5',
            'driver_rating' => 'required|numeric|between:0,5',
            'comments' => 'nullable|string|max:1000',
            'issues_reported' => 'nullable|string|max:1000',
            'resolution_status' => 'required|in:pending,resolved,dismissed',
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
            'passenger_rating.required' => 'Passenger rating is required.',
            'passenger_rating.between' => 'Passenger rating must be between 0 and 5.',
            'driver_rating.required' => 'Driver rating is required.',
            'driver_rating.between' => 'Driver rating must be between 0 and 5.',
            'resolution_status.in' => 'Resolution status must be pending, resolved, or dismissed.',
        ];
    }
}
