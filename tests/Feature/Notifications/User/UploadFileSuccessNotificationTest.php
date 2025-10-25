<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\User\UploadFileSuccessNotification;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('uses mail channel only', function (): void {
    $notification = new UploadFileSuccessNotification();

    expect($notification->via($this->user))->toBe(['mail']);
});

it('builds the correct mail message without attachments', function (): void {
    $notification = new UploadFileSuccessNotification();

    $mail = $notification->toMail($this->user);

    expect($mail->subject)->toBe(__('Your CSV was processed successfully'))
        ->and($mail->markdown)->toBe('emails.upload_success')
        ->and($mail->attachments)->toBeArray()
        ->and($mail->attachments)->toHaveCount(0);
});

it('returns empty array representation', function (): void {
    $notification = new UploadFileSuccessNotification();

    expect($notification->toArray($this->user))->toBe([]);
});
