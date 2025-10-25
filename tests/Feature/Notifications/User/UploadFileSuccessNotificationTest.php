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

    // Subject and markdown view
    expect($mail->subject)->toBe(__('File upload completed successfully'))
        ->and($mail->markdown)->toBe('emails.upload_success');

    // No attachments should be added
    expect($mail->attachments)->toBeArray()
        ->and($mail->attachments)->toHaveCount(0);
});

it('returns empty array representation', function (): void {
    $notification = new UploadFileSuccessNotification();

    expect($notification->toArray($this->user))->toBe([]);
});
