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
        // Expected structure for each $item: ['data' => [...], 'errors' => [...]]
        // Fixed headers order as requested: name, email, cpf, city, state, then errors
        $headers = ['name', 'email', 'cpf', 'city', 'state', 'errors'];
        $rows = [];

        foreach ($errors as $item) {
            $item = is_array($item) ? $item : [];
            $payload = $item['data'] ?? [];

            // Initialize row with empty values for all known headers (except 'errors')
            $row = [
                'name' => '',
                'email' => '',
                'cpf' => '',
                'city' => '',
                'state' => '',
            ];

            // Map payload into fixed columns, supporting associative and indexed arrays
            if (is_array($payload)) {
                $isList = array_keys($payload) === range(0, count($payload) - 1);
                if ($isList) {
                    // Indexed: assume positions 0..4 map to name,email,cpf,city,state
                    $map = ['name', 'email', 'cpf', 'city', 'state'];
                    foreach ($map as $i => $key) {
                        if (array_key_exists($i, $payload)) {
                            $row[$key] = $payload[$i];
                        }
                    }
                } else {
                    // Associative: use provided keys if present
                    foreach (['name', 'email', 'cpf', 'city', 'state'] as $key) {
                        if (array_key_exists($key, $payload)) {
                            $row[$key] = $payload[$key];
                        }
                    }
                }
            }

            // Build a single 'errors' text joining messages by ';'
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
            $row['errors'] = $err;

            $rows[] = $row;
        }

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
