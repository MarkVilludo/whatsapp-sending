<?php

namespace App\Services;

use App\Models\Message;
use App\Models\PhoneNumber;
use App\Models\Provider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfobipSmsService
{
    private const STATUS_SENT = 'sent';
    private const STATUS_DELIVERED = 'delivered';
    private const STATUS_RECEIVED = 'received';
    private const STATUS_FAILED = 'failed';

    public function sendTextMessage(string $from, string $to, string $text): array
    {
        $provider = Provider::firstOrCreate(
            ['name' => 'Infobip'],
            ['type' => 'whatsapp']
        );

        $fromNumber = PhoneNumber::firstOrCreate(
            ['number' => $from],
            ['provider_id' => $provider->id]
        );

        $toNumber = PhoneNumber::firstOrCreate(
            ['number' => $to],
            ['provider_id' => $provider->id]
        );

        $endpoint = rtrim((string) config('services.infobip.base_url'), '/').'/whatsapp/1/message/text';
        $apiKey = (string) config('services.infobip.api_key');

        $payload = [
            'messages' => [
                [
                    'from' => $from,
                    'to' => $to,
                    'content' => [
                        'text' => $text,
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'App '.$apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload)->throw();
        } catch (RequestException $exception) {
            $errorBody = $exception->response?->json() ?? $exception->response?->body();

            Message::create([
                'from_number_id' => $fromNumber->id,
                'to_number_id' => $toNumber->id,
                'provider_id' => $provider->id,
                'direction' => 'outgoing',
                'message_text' => $text,
                'status' => self::STATUS_FAILED,
                'sent_at' => now(),
                'provider_message_id' => 'error-'.(string) now()->timestamp.'-'.substr((string) md5($from.$to.$text), 0, 10),
                'logs' => $this->encodeLogs([
                    'request' => $payload,
                    'error' => $errorBody,
                ]),
            ]);

            Log::error('Infobip Error: '.$exception->response?->body());
            throw $exception;
        }

        $responseBody = $response->json() ?? [];
        $messageResult = Arr::get($responseBody, 'messages.0', []);
        $status = $this->normalizeStatusFromResponse($messageResult, 'outgoing');
        $messageId = (string) (Arr::get($messageResult, 'messageId') ?? ('outgoing-'.(string) now()->timestamp.'-'.substr((string) md5($from.$to.$text), 0, 10)));

        Log::info('Infobip Response', ['response' => $responseBody]);

        Message::create([
            'from_number_id' => $fromNumber->id,
            'to_number_id' => $toNumber->id,
            'provider_id' => $provider->id,
            'direction' => 'outgoing',
            'message_text' => $text,
            'status' => $status,
            'provider_message_id' => $messageId,
            'logs' => $this->encodeLogs([
                'request' => $payload,
                'response' => $responseBody,
            ]),
            'sent_at' => now(),
        ]);

        return $responseBody;
    }

    public function handleIncomingWebhook(array $payload): void
    {
        $provider = Provider::firstOrCreate(
            ['name' => 'Infobip'],
            ['type' => 'whatsapp']
        );

        $results = $payload['results'] ?? [];

        foreach ($results as $result) {
            $from = (string) ($result['from'] ?? '');
            $to = (string) ($result['to'] ?? '');
            $messageObj = $result['message'] ?? [];
            $text = (string) ($messageObj['text'] ?? '');
            $messageId = (string) ($result['messageId'] ?? '');
            $normalizedStatus = $this->normalizeStatusFromResponse($result, 'incoming');
            $encodedLogs = $this->encodeLogs([
                'webhook' => $payload,
                'result' => $result,
            ]);

            $outgoingStatusReport = $messageId !== '' && isset($result['status']) && $text === '';
            if ($outgoingStatusReport) {
                $existing = Message::query()
                    ->where('provider_message_id', $messageId)
                    ->first();

                if ($existing !== null) {
                    $existing->update([
                        'status' => $normalizedStatus,
                        'logs' => $encodedLogs,
                    ]);
                } else {
                    if ($from === '' || $to === '') {
                        continue;
                    }

                    $fromNumber = PhoneNumber::firstOrCreate(
                        ['number' => $from],
                        ['provider_id' => $provider->id]
                    );

                    $toNumber = PhoneNumber::firstOrCreate(
                        ['number' => $to],
                        ['provider_id' => $provider->id]
                    );

                    Message::create([
                        'from_number_id' => $fromNumber->id,
                        'to_number_id' => $toNumber->id,
                        'provider_id' => $provider->id,
                        'direction' => 'outgoing',
                        'message_text' => '[STATUS REPORT]',
                        'status' => $normalizedStatus,
                        'provider_message_id' => $messageId,
                        'logs' => $encodedLogs,
                        'sent_at' => now(),
                    ]);
                }

                continue;
            }

            if ($from === '' || $to === '' || $text === '') {
                continue;
            }

            $fromNumber = PhoneNumber::firstOrCreate(
                ['number' => $from],
                ['provider_id' => $provider->id]
            );

            $toNumber = PhoneNumber::firstOrCreate(
                ['number' => $to],
                ['provider_id' => $provider->id]
            );

            Message::create([
                'from_number_id' => $fromNumber->id,
                'to_number_id' => $toNumber->id,
                'provider_id' => $provider->id,
                'direction' => 'incoming',
                'message_text' => $text,
                'status' => self::STATUS_RECEIVED,
                'provider_message_id' => $messageId !== '' ? $messageId : 'incoming-'.(string) now()->timestamp.'-'.substr((string) md5($from.$to.$text), 0, 10),
                'logs' => $encodedLogs,
                'received_at' => now(),
            ]);
        }
    }

    public function sendTemplateMessage(
        string $from,
        string $to,
        string $templateName,
        array $placeholders = [],
        string $language = 'en',
        ?array $templateData = null
    ): array {
        $provider = Provider::firstOrCreate(
            ['name' => 'Infobip'],
            ['type' => 'whatsapp']
        );

        $fromNumber = PhoneNumber::firstOrCreate(
            ['number' => $from],
            ['provider_id' => $provider->id]
        );

        $toNumber = PhoneNumber::firstOrCreate(
            ['number' => $to],
            ['provider_id' => $provider->id]
        );

        $endpoint = rtrim((string) config('services.infobip.base_url'), '/').'/whatsapp/1/message/template';
        $apiKey = (string) config('services.infobip.api_key');

        $resolvedTemplateData = $templateData ?? [
            'body' => [
                'placeholders' => $placeholders,
            ],
        ];

        $payload = [
            'messages' => [
                [
                    'from' => $from,
                    'to' => $to,
                    'content' => [
                        'templateName' => $templateName,
                        'templateData' => $resolvedTemplateData,
                        'language' => $language,
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'App '.$apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withoutVerifying() 
            ->post($endpoint, $payload)
            ->throw();

        } catch (RequestException $exception) {
            $errorBody = $exception->response?->body();
            Log::error("Infobip API Error for $from: " . $errorBody);

            Message::create([
                'from_number_id' => $fromNumber->id,
                'to_number_id' => $toNumber->id,
                'provider_id' => $provider->id,
                'direction' => 'outgoing',
                'message_text' => '[TEMPLATE: '.$templateName.'] '.implode(' | ', $placeholders),
                'status' => self::STATUS_FAILED,
                'provider_message_id' => 'error-'.(string) now()->timestamp.'-'.substr((string) md5($from.$to.$templateName), 0, 10),
                'logs' => $this->encodeLogs([
                    'request' => $payload,
                    'error' => $exception->response?->json() ?? $errorBody,
                ]),
                'sent_at' => now(),
            ]);

            throw $exception;
        }

        $responseData = $response->json() ?? [];

        $messageResult = $responseData['messages'][0] ?? [];

        if ($messageResult) {
            $externalId = (string) ($messageResult['messageId'] ?? ('outgoing-'.(string) now()->timestamp.'-'.substr((string) md5($from.$to.$templateName), 0, 10)));
            $normalizedStatus = $this->normalizeStatusFromResponse($messageResult, 'outgoing');

            Message::create([
                'from_number_id' => $fromNumber->id,
                'to_number_id'   => $toNumber->id,
                'provider_id'    => $provider->id,
                'direction'      => 'outgoing',
                'message_text'   => '[TEMPLATE: '.$templateName.'] '.implode(' | ', $placeholders),
                'status'         => $normalizedStatus,
                'sent_at'        => now(),
                'provider_message_id' => $externalId,
                'logs' => $this->encodeLogs([
                    'request' => $payload,
                    'response' => $responseData,
                ]),
            ]);
        }

        return $responseData ?? [];
    }

    public function sendSms(string $from, string $to, string $text): array
    {
        return $this->sendTextMessage($from, $to, $text);
    }

    private function normalizeStatusFromResponse(array $result, string $direction): string
    {
        $groupId = strtoupper((string) Arr::get($result, 'status.groupId', ''));
        $name = strtoupper((string) Arr::get($result, 'status.name', ''));
        $description = strtoupper((string) Arr::get($result, 'status.description', ''));
        $reason = strtoupper((string) Arr::get($result, 'reason', ''));

        $failedMarkers = ['REJECTED', 'FAILED', 'UNDELIVERABLE', 'ERROR'];
        foreach ($failedMarkers as $marker) {
            if (
                str_contains($groupId, $marker) ||
                str_contains($name, $marker) ||
                str_contains($description, $marker) ||
                str_contains($reason, $marker)
            ) {
                return self::STATUS_FAILED;
            }
        }

        if (in_array($groupId, ['DELIVERED', 'DELIVERED_TO_HANDSET'], true)) {
            return self::STATUS_DELIVERED;
        }

        if (in_array($groupId, ['PENDING', 'ACCEPTED'], true)) {
            return self::STATUS_SENT;
        }

        return $direction === 'incoming' ? self::STATUS_RECEIVED : self::STATUS_SENT;
    }

    private function encodeLogs(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
