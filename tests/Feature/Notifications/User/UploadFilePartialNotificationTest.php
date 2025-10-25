<?php

declare(strict_types=1);

use App\Enums\BatchEnum;
use App\Models\User;
use App\Notifications\User\UploadFilePartialNotification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->batchId = (string) Str::uuid();
});

it('uses mail channel only (partial notification)', function (): void {
    $notification = new UploadFilePartialNotification($this->batchId);

    expect($notification->via($this->user))->toBe(['mail']);
});

it('builds mail with CSV attachment from batch histories (indexed and associative payloads, errors flattened)', function (): void {
    // History 1: indexed payload + nested array errors
    $this->user->batch()->create([
        'type' => BatchEnum::EMPLOYEE_BULK_STORE,
        'batch_id' => $this->batchId,
        'data' => [
            'data' => ['John Doe', 'john@example.com', '52998224725', 'City X', 'ST'],
            'errors' => [
                'email' => ['taken', 'invalid'],
                'cpf' => ['The cpf field is invalid.'],
                'other' => [['a', 'b'], 'c'],
            ],
        ],
    ]);

    // History 2: associative payload + string error
    $this->user->batch()->create([
        'type' => BatchEnum::EMPLOYEE_BULK_STORE,
        'batch_id' => $this->batchId,
        'data' => [
            'data' => [
                'name' => 'Jane Roe',
                'email' => 'jane@example.com',
                'cpf' => '11111111111',
                'city' => 'Town Y',
                'state' => 'SP',
            ],
            'errors' => 'simple error',
        ],
    ]);

    $notification = new UploadFilePartialNotification($this->batchId);

    $mail = $notification->toMail($this->user);

    // Subject, markdown and attachment presence
    expect($mail->subject)->toBe(__('Your file was processed with partial success'))
        ->and($mail->markdown)->toBe('emails.upload_partial')
        // Laravel stores attachData in rawAttachments
        ->and($mail->rawAttachments)->toBeArray()
        ->and($mail->rawAttachments)->toHaveCount(1);

    $attachment = $mail->rawAttachments[0];

    // Attachment meta
    expect($attachment['name'])->toBe(sprintf('batch-errors-%s.csv', $this->batchId))
        ->and($attachment['options']['mime'])->toBe('text/csv; charset=UTF-8');

    // Attachment content
    $csv = $attachment['data'];

    // Starts with UTF-8 BOM
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    // Strip BOM for simpler asserts
    $csvNoBom = mb_substr($csv, 1);
    // Normalize line endings and split into lines
    $normalized = mb_rtrim($csvNoBom, "\r\n");
    $lines = array_values(array_filter(preg_split("/\r\n|\n|\r/", $normalized)));

    // Header line
    expect($lines[0])->toBe('name;email;cpf;city;state;errors')
        ->and($lines[1])->toBe('"John Doe";john@example.com;52998224725;"City X";ST;"taken|invalid|The cpf field is invalid.|a|b|c"')
        ->and($lines[2])->toBe('"Jane Roe";jane@example.com;11111111111;"Town Y";SP;"simple error"');
});

it('toArray returns batch_id only', function (): void {
    $notification = new UploadFilePartialNotification($this->batchId);

    expect($notification->toArray($this->user))->toBe([
        'batch_id' => $this->batchId,
    ]);
});

it('when there are no histories for batch, attaches CSV with only header', function (): void {
    $notification = new UploadFilePartialNotification($this->batchId);

    $mail = $notification->toMail($this->user);

    expect($mail->rawAttachments)->toHaveCount(1);
    $attachment = $mail->rawAttachments[0];

    $csv = $attachment['data'];
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    $csvNoBom = mb_substr($csv, 1);
    // Only header and trailing newline
    expect($csvNoBom)->toBe("name;email;cpf;city;state;errors\n");
});

it('handles non-array payloads, missing errors, and JSON-encodes complex values', function (): void {
    // History 1: non-array top-level data, no errors key
    $this->user->batch()->create([
        'type' => BatchEnum::EMPLOYEE_BULK_STORE,
        'batch_id' => $this->batchId,
        'data' => 'not-an-array',
    ]);

    // History 2: associative payload with complex values + array errors
    $this->user->batch()->create([
        'type' => BatchEnum::EMPLOYEE_BULK_STORE,
        'batch_id' => $this->batchId,
        'data' => [
            'data' => [
                'name' => ['nested'],                 // array -> JSON
                'email' => (object) ['data' => 1],       // object -> JSON
                'cpf' => 123,                          // scalar -> string
                'city' => null,                        // null -> empty
                'state' => ['a' => 'b'],               // assoc array -> JSON
            ],
            'errors' => ['x'],
        ],
    ]);

    $notification = new UploadFilePartialNotification($this->batchId);
    $mail = $notification->toMail($this->user);

    $attachment = $mail->rawAttachments[0];
    $csv = $attachment['data'];

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();
    $csvNoBom = mb_substr($csv, 1);

    $normalized = mb_rtrim($csvNoBom, "\r\n");
    $lines = array_values(array_filter(preg_split("/\r\n|\n|\r/", $normalized)));

    // Expect header + two rows
    expect(count($lines))->toBe(3)
        ->and($lines[0])->toBe('name;email;cpf;city;state;errors');

    // Parse rows via CSV to decode quotes properly
    $row1 = str_getcsv($lines[1], ';');
    $row2 = str_getcsv($lines[2], ';');

    expect($row1)->toBe(['', '', '', '', '', ''])
        ->and($row2[0])->toBe('["nested"]')
        ->and($row2[1])->toBe('{"data":1}')
        ->and($row2[2])->toBe('123')
        ->and($row2[3])->toBe('')
        ->and($row2[4])->toBe('{"a":"b"}')
        ->and($row2[5])->toBe('x');
});
