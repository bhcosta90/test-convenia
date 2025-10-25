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
        // We expect each $item to be something like: ['data' => [...], 'errors' => [...]]
        // Goal: Expand 'data' into multiple columns and place 'error' as the last column.
        $rows = [];
        $dataKeys = [];

        foreach ($errors as $item) {
            $item = is_array($item) ? $item : [];
            $payload = $item['data'] ?? [];

            $row = [];

            // Expand 'data' into columns
            if (is_array($payload)) {
                $isList = array_keys($payload) === range(0, count($payload) - 1);
                if ($isList) {
                    // Numeric array: name columns as data_1..data_N
                    foreach ($payload as $idx => $value) {
                        $col = 'data_'.($idx + 1);
                        $dataKeys[$col] = true;
                        $row[$col] = $value;
                    }
                } else {
                    // Associative array: use actual keys
                    foreach ($payload as $key => $value) {
                        $dataKeys[$key] = true;
                        $row[$key] = $value;
                    }
                }
            }

            // Build a single 'error' text joining messages by ';'
            $err = '';
            if (array_key_exists('errors', $item)) {
                if (is_array($item['errors'])) {
                    $flat = [];
                    $iterator = function ($v) use (&$flat, &$iterator) {
                        if (is_array($v)) {
                            foreach ($v as $vv) {
                                $iterator($vv);
                            }
                        } else {
                            $v = (string) $v;
                            if ($v !== '') {
                                $flat[] = $v;
                            }
                        }
                    };
                    $iterator($item['errors']);
                    $err = implode(';', $flat);
                } else {
                    $err = (string) $item['errors'];
                }
            }
            $row['error'] = $err;

            $rows[] = $row;
        }

        // Finalize headers: all data keys first, then 'error'
        $headers = array_keys($dataKeys);
        $headers[] = 'error';

        // Build CSV with UTF-8 BOM and semicolon delimiter for better Excel compatibility
        $out = fopen('php://temp', 'r+');
        // Write BOM
        fwrite($out, "\xEF\xBB\xBF");
        // Header
        fputcsv($out, $headers, ';', '"', '\\');
        // Rows
        foreach ($rows as $row) {
            $ordered = [];
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $ordered[] = (string) $val;
            }
            fputcsv($out, $ordered, ';', '"', '\\');
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
