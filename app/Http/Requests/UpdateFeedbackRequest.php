<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Update with your authorization logic if needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'passenger_rating' => 'sometimes|numeric|between:0,5',
            'driver_rating' => 'sometimes|numeric|between:0,5',
            'comments' => 'sometimes|string|max:1000',
            'issues_reported' => 'sometimes|string|max:1000',
            'resolution_status' => 'sometimes|in:pending,resolved,dismissed',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'passenger_rating.numeric' => 'Passenger rating must be a number.',
            'passenger_rating.between' => 'Passenger rating must be between 0 and 5.',
            'driver_rating.numeric' => 'Driver rating must be a number.',
            'driver_rating.between' => 'Driver rating must be between 0 and 5.',
            'comments.string' => 'Comments must be a valid string.',
            'issues_reported.string' => 'Issues reported must be a valid string.',
            'resolution_status.in' => 'Resolution status must be one of the following: pending, resolved, dismissed.',
        ];
    }
}
