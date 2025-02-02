<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewRideNotification extends Notification
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
        return ['database']; // Adjust based on your needs
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
            'title' => 'new ride',
            'message' => 'A new ride is available!',
            'ride_details' => $this->rideDetails,
        ];
    }
}
