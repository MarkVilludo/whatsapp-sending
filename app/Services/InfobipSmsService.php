<?php

namespace App\Services;

use App\Models\Message;
use App\Models\PhoneNumber;
use App\Models\Provider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfobipSmsService
{
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
            Message::create([
                'from_number_id' => $fromNumber->id,
                'to_number_id' => $toNumber->id,
                'provider_id' => $provider->id,
                'direction' => 'outgoing',
                'message_text' => $text,
                'status' => 'failed',
                'sent_at' => now(),
            ]);

            Log::error('Infobip Error: '.$exception->response?->body());
            throw $exception;
        }

        $responseBody = $response->json() ?? [];

        Message::create([
            'from_number_id' => $fromNumber->id,
            'to_number_id' => $toNumber->id,
            'provider_id' => $provider->id,
            'direction' => 'outgoing',
            'message_text' => $text,
            'status' => 'sent',
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
            $text = (string) ($result['text'] ?? '');

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
                'status' => 'received',
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

        if (isset($resolvedTemplateData['header']) && is_array($resolvedTemplateData['header'])) {
            $mediaUrl = $resolvedTemplateData['header']['mediaUrl'] ?? null;
            if (! is_string($mediaUrl) || filter_var($mediaUrl, FILTER_VALIDATE_URL) === false) {
                unset($resolvedTemplateData['header']);
            }
        }

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
            ])->post($endpoint, $payload)->throw();
        } catch (RequestException $exception) {
            Message::create([
                'from_number_id' => $fromNumber->id,
                'to_number_id' => $toNumber->id,
                'provider_id' => $provider->id,
                'direction' => 'outgoing',
                'message_text' => '[TEMPLATE: '.$templateName.'] '.implode(' | ', $placeholders),
                'status' => 'failed',
                'sent_at' => now(),
            ]);

            Log::error('Infobip Error: '.$exception->response?->body());
            throw $exception;
        }

        Message::create([
            'from_number_id' => $fromNumber->id,
            'to_number_id' => $toNumber->id,
            'provider_id' => $provider->id,
            'direction' => 'outgoing',
            'message_text' => '[TEMPLATE: '.$templateName.'] '.implode(' | ', $placeholders),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $response->json() ?? [];
    }

    public function sendSms(string $from, string $to, string $text): array
    {
        return $this->sendTextMessage($from, $to, $text);
    }
}
