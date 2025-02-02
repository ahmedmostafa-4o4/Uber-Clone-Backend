<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDriverNotification extends Notification
{
    use Queueable;

    private $driverDetails;

    /**
     * Create a new notification instance.
     *
     * @param array $driverDetails
     */
    public function __construct($driverDetails)
    {
        $this->driverDetails = $driverDetails;
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
            'title' => 'New Driver',
            'message' => 'A new driver registered!',
            'driver_details' => $this->driverDetails,
        ];
    }
}
