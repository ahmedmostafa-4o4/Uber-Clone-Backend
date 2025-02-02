<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRideRequest extends FormRequest
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
            'passenger_id' => 'required|exists:passengers,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'region' => 'required|string',
            'pickup_location' => 'required',
            'pickup_location.latitude' => 'required|numeric',
            'pickup_location.longitude' => 'required|numeric',
            'dropoff_location' => 'required',
            'dropoff_location.latitude' => 'required|numeric',
            'dropoff_location.longitude' => 'required|numeric',
            'start_time' => 'nullable|date|before_or_equal:end_time',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'distance' => 'required|numeric|min:0',
            'fare' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,in_progress,completed,canceled,going_to_passenger,accepted,arrived',
        ];
    }

    public function messages(): array
    {
        return [
            'passenger_id.required' => 'The passenger is required.',
            'passenger_id.exists' => 'The selected passenger does not exist.',
            'driver_id.required' => 'The driver is required.',
            'driver_id.exists' => 'The selected driver does not exist.',
            'pickup_location.required' => 'Pickup location is required.',
            'pickup_location.array' => 'Pickup location must be an array.',
            'pickup_location.lat.required' => 'The latitude of the pickup location is required.',
            'pickup_location.lng.required' => 'The longitude of the pickup location is required.',
            'dropoff_location.required' => 'Dropoff location is required.',
            'dropoff_location.array' => 'Dropoff location must be an array.',
            'distance.required' => 'Distance is required.',
            'distance.min' => 'Distance must be at least 0.',
            'fare.required' => 'Fare is required.',
            'fare.min' => 'Fare must be at least 0.',
            'status.in' => 'The selected ride status is invalid.',
        ];
    }
}
