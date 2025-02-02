<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OTPNotification extends Notification
{
    use Queueable;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function via($notifiable)
    {
        // Choose the channels: "mail" for email, "sms" for SMS
        return ['mail']; // Add 'nexmo' or 'twilio' for SMS
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your OTP Code')
            ->line("Your OTP is: {$this->otp}")
            ->line('This OTP is valid for 5 minutes.');
    }

   
}
