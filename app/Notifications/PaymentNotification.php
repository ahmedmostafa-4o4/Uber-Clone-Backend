<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

    private $paymentDetails;
    public function __construct($paymentDetails)
    {
        $this->paymentDetails = $paymentDetails;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Notification')
            ->greeting('Hello ' . $this->paymentDetails['name'])
            ->line('You have made a payment of ' . $this->paymentDetails['amount'] . ' ' . $this->paymentDetails['currency'])
            ->line('Payment method: ' . $this->paymentDetails['payment_method'])
            ->line('Payment status: ' . $this->paymentDetails['status'])
            ->line('Ride details: ') // Convert to string
            ->line('    ID: ' . $this->paymentDetails['ride_details']['id'])
            ->line('    Created At: ' . $this->paymentDetails['ride_details']['created_at'])
            ->line('    Status: ' . $this->paymentDetails['ride_details']['status'])
            ->line('    Fare: ' . $this->paymentDetails['ride_details']['fare'])
            ->line('    Distance: ' . $this->paymentDetails['ride_details']['distance'])
            ->line('    Passenger Name: ' . $this->paymentDetails['ride_details']['passenger_id'])
            ->line('    Driver Name: ' . $this->paymentDetails['ride_details']['driver_id'])
            ->line('Payment intent ID: ' . ($this->paymentDetails['payment_intent_id'] ?? 'N/A')) // Avoid error if missing
            ->line('Thanks for using our service!');
    }
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment Notification',
            'message' => 'You have made a payment of ' . $this->paymentDetails['amount'],
            'ride_details' => $this->paymentDetails['ride_details'],
            'payment_intent_id' => $this->paymentDetails['payment_intent_id'] ?? 'N/A',
            'status' => $this->paymentDetails['status'],
            'payment_method' => $this->paymentDetails['payment_method'] ?? 'N/A',
            'name' => $this->paymentDetails['name'],
            'currency' => $this->paymentDetails['currency'],

        ];
    }
}
