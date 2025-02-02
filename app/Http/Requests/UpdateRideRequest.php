<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Update as needed for authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'driver_id' => 'sometimes|exists:drivers,id',
            'region' => 'sometimes',
            'pickup_location' => 'sometimes',
            'pickup_location.lat' => 'sometimes|numeric|between:-90,90',
            'pickup_location.lng' => 'sometimes|numeric|between:-180,180',
            'dropoff_location' => 'sometimes',
            'dropoff_location.lat' => 'sometimes|numeric|between:-90,90',
            'dropoff_location.lng' => 'sometimes|numeric|between:-180,180',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'distance' => 'sometimes|numeric|min:0',
            'fare' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:pending,in_progress,completed,canceled,going_to_passenger,accepted,arrived',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'passenger_id.exists' => 'The selected passenger does not exist.',
            'driver_id.exists' => 'The selected driver does not exist.',
            'pickup_location.array' => 'Pickup location must be an array.',
            'pickup_location.lat.numeric' => 'Pickup location latitude must be a number.',
            'pickup_location.lng.numeric' => 'Pickup location longitude must be a number.',
            'dropoff_location.array' => 'Dropoff location must be an array.',
            'distance.numeric' => 'Distance must be a number.',
            'distance.min' => 'Distance must be at least 0.',
            'fare.numeric' => 'Fare must be a number.',
            'fare.min' => 'Fare must be at least 0.',
            'status.in' => 'The selected ride status is invalid.',
        ];
    }
}
