<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpdateRideNotification extends Notification
{
    use Queueable;

    private $rideDetails;

    /**
     * Create a new notification instance.
     *
     * @param array $rideDetails
     */
    public function __construct($rideDetails)
    {
        $this->rideDetails = $rideDetails;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database']; // Adjust based on your needs
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Updated Ride')
            ->line('An updated ride is available!')
            ->line('Ride ID: ' . $this->rideDetails['id'])
            ->line('Driver Name: ' . $this->rideDetails['driver'])
            ->line('Passenger Name: ' . $this->rideDetails['passenger'])
            ->line('Start Time: ' . $this->rideDetails['start_time'])
            ->line('End Time: ' . $this->rideDetails['end_time'])
            ->line('Distance: ' . $this->rideDetails['distance'])
            ->line('Status: ' . $this->rideDetails['status']);





    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => 'updated ride',
            'message' => 'An updated ride is available!',
            'ride_details' => $this->rideDetails,
        ];
    }
}
