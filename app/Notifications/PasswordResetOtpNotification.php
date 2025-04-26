<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    /**
     * The OTP code.
     *
     * @var string
     */
    protected $otp;

    /**
     * The password reset token.
     *
     * @var string|null
     */
    protected $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, string $token = null)
    {
        $this->otp = $otp;
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Password Reset')
            ->greeting('Hello!')
            ->line('You are receiving this email because we received a password reset request for your account.');
            
        // Add OTP information
        $message->line('Your password reset OTP code is: ' . $this->otp)
                ->line('This OTP code will expire in 15 minutes.');
        
        // Add reset link if token is provided
        if ($this->token) {
            $url = url(route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            
            $message->line('Alternatively, you can click the button below to reset your password:')
                    ->action('Reset Password', $url)
                    ->line('This password reset link will expire in ' . config('auth.passwords.'.config('auth.defaults.passwords').'.expire') . ' minutes.');
        }
        
        $message->line('If you did not request a password reset, no further action is required.');
        
        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'otp' => $this->otp,
            'token' => $this->token
        ];
    }
}
