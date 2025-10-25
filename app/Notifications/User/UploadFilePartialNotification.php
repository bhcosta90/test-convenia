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

        // Build CSV from aggregated errors
        $csv = $this->buildCsvFromBatchErrors($errors);
        $filename = $this->csvFilename();

        return (new MailMessage)
            ->subject(__('Your file was processed with partial success'))
            ->markdown('emails.upload_partial', [
                'batchId' => $this->batchId,
                'filename' => $filename,
            ])
            ->attachData($csv, $filename, [
                'mime' => 'text/csv; charset=UTF-8',
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'batch_id' => $this->batchId,
        ];
    }

    private function buildCsvFromBatchErrors(array $errors): string
    {
        // Normalize records and collect all keys from 'data' while preparing an 'error' field
        $rows = [];
        $allKeys = [];

        foreach ($errors as $item) {
            // Each $item is expected to be an associative array; 'errors' may be an array or string.
            $data = is_array($item) ? $item : [];

            $row = [];
            foreach ($data as $key => $value) {
                if ($key === 'errors') {
                    // normalize later into 'error' column
                    continue;
                }
                $allKeys[$key] = true;
                $row[$key] = $value;
            }

            // Build the 'error' column as a semicolon-joined list
            $err = '';
            if (isset($data['errors'])) {
                if (is_array($data['errors'])) {
                    // Flatten potential nested arrays to strings
                    $flat = [];
                    $iterator = function ($v) use (&$flat, &$iterator) {
                        if (is_array($v)) {
                            foreach ($v as $vv) {
                                $iterator($vv);
                            }
                        } else {
                            $flat[] = (string) $v;
                        }
                    };
                    $iterator($data['errors']);
                    $err = implode(';', array_filter($flat, fn ($s) => $s !== ''));
                } else {
                    $err = (string) $data['errors'];
                }
            }
            $row['error'] = $err;

            $rows[] = $row;
        }

        // Ensure 'error' is part of headers and placed at the end
        $allKeys['error'] = true;
        $headers = array_keys($allKeys);

        // Build CSV string
        $out = fopen('php://temp', 'r+');
        // Write header
        fputcsv($out, $headers);
        // Write rows
        foreach ($rows as $row) {
            $ordered = [];
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                // Normalize arrays/objects as JSON strings
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $ordered[] = (string) $val;
            }
            fputcsv($out, $ordered);
        }
        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        return $csv;
    }

    private function csvFilename(): string
    {
        return sprintf('batch_errors_%s.csv', $this->batchId);
    }
}
