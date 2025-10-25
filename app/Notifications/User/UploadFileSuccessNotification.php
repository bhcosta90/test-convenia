<?php

declare(strict_types=1);

namespace App\Notifications\User;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UploadFileSuccessNotification extends Notification
{
    public function __construct() {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('File upload completed successfully'))
            ->markdown('emails.upload_success');
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
