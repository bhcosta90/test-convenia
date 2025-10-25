<?php

declare(strict_types=1);

namespace App\Notifications\User;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UploadFilePartialNotification extends Notification
{
    public function __construct(protected string $batchId) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // Collect all batch history error payloads for this user and batch id
        $histories = $notifiable->batch()->where('batch_id', $this->batchId)->get(['data']);
        $errors = $histories->pluck('data')->values()->all();

        $errorsJson = json_encode(['errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return (new MailMessage)
            ->subject(__('Your file was processed with partial success'))
            ->markdown('emails.upload_partial', [
                'batchId' => $this->batchId,
                'errorsJson' => $errorsJson,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'batch_id' => $this->batchId,
        ];
    }
}
