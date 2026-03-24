<?php

namespace App\Services;

use App\Models\Message;
use App\Models\PhoneNumber;
use App\Models\Provider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                'provider_message_id' => (string) Str::uuid(),
                'logs' => $exception->response?->body(),
            ]);

            Log::error('Infobip Error: '.$exception->response?->body());
            throw $exception;
        }

        $responseBody = $response->json() ?? [];
        $firstResult = is_array($responseBody['messages'][0] ?? null) ? $responseBody['messages'][0] : [];
        $providerMessageId = (string) ($responseBody['messages'][0]['messageId'] ?? Str::uuid());
        $resolvedStatus = $this->mapWebhookStatusToLocal($firstResult) ?? 'sent';

        Message::create([
            'from_number_id' => $fromNumber->id,
            'to_number_id' => $toNumber->id,
            'provider_id' => $provider->id,
            'direction' => 'outgoing',
            'message_text' => $text,
            'status' => $resolvedStatus,
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
            'logs' => json_encode($responseBody),
        ]);

        return $responseBody;
    }

    public function handleIncomingWebhook(array $payload): void
    {
        $provider = Provider::firstOrCreate(
            ['name' => 'Infobip'],
            ['type' => 'whatsapp']
        );

        $results = $this->extractWebhookResults($payload);

        foreach ($results as $result) {
            $to = (string) ($result['to'] ?? '');
            $from = (string) ($result['from'] ?? '');
            $text = $this->extractIncomingText($result);
            $providerMessageId = (string) ($result['messageId'] ?? '');
            $webhookStatus = $this->mapWebhookStatusToLocal($result);

            if ($to !== '' && $webhookStatus !== null && ($from === '' || $text === '')) {
                $latestOutgoing = null;

                if ($providerMessageId !== '') {
                    $latestOutgoing = Message::query()
                        ->where('provider_id', $provider->id)
                        ->where('direction', 'outgoing')
                        ->where('provider_message_id', $providerMessageId)
                        ->latest()
                        ->first();
                }

                if ($latestOutgoing === null) {
                    $latestOutgoing = Message::query()
                        ->where('provider_id', $provider->id)
                        ->where('direction', 'outgoing')
                        ->whereHas('toNumber', fn ($query) => $query->where('number', $to))
                        ->latest()
                        ->first();
                }

                if ($latestOutgoing !== null) {
                    $latestOutgoing->status = $webhookStatus;

                    if ($webhookStatus === 'delivered') {
                        $latestOutgoing->received_at = now();
                    }

                    if ($providerMessageId !== '') {
                        $latestOutgoing->provider_message_id = $providerMessageId;
                    }

                    $latestOutgoing->logs = json_encode($result);

                    $latestOutgoing->save();
                } else {
                    // If send response was not persisted for any reason, keep webhook status in DB.
                    $toNumber = PhoneNumber::firstOrCreate(
                        ['number' => $to],
                        ['provider_id' => $provider->id]
                    );

                    Message::create([
                        'from_number_id' => $toNumber->id,
                        'to_number_id' => $toNumber->id,
                        'provider_id' => $provider->id,
                        'direction' => 'outgoing',
                        'message_text' => '[WEBHOOK STATUS UPDATE] '.$webhookStatus,
                        'status' => $webhookStatus,
                        'sent_at' => now(),
                        'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : (string) Str::uuid(),
                        'logs' => json_encode($result),
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
                'status' => 'received',
                'received_at' => now(),
                'provider_message_id' => (string) ($result['messageId'] ?? Str::uuid()),
                'logs' => json_encode($result),
            ]);
        }
    }

    private function extractIncomingText(array $result): string
    {
        $directText = (string) ($result['text'] ?? '');
        if ($directText !== '') {
            return $directText;
        }

        $messageText = (string) ($result['message']['text'] ?? '');
        if ($messageText !== '') {
            return $messageText;
        }

        $contentText = (string) ($result['content']['text'] ?? '');
        if ($contentText !== '') {
            return $contentText;
        }

        $interactiveTitle = (string) ($result['message']['interactive']['buttonReply']['title'] ?? '');
        if ($interactiveTitle !== '') {
            return $interactiveTitle;
        }

        $interactiveListTitle = (string) ($result['message']['interactive']['listReply']['title'] ?? '');
        if ($interactiveListTitle !== '') {
            return $interactiveListTitle;
        }

        $caption = (string) ($result['message']['image']['caption'] ?? '');
        if ($caption !== '') {
            return $caption;
        }

        return '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractWebhookResults(array $payload): array
    {
        $results = $payload['results'] ?? null;
        if (is_array($results)) {
            return $results;
        }

        $webhookResults = $payload['webhook']['results'] ?? null;
        if (is_array($webhookResults)) {
            return $webhookResults;
        }

        $singleResult = $payload['result'] ?? null;
        if (is_array($singleResult)) {
            return [$singleResult];
        }

        return [];
    }

    private function mapWebhookStatusToLocal(array $result): ?string
    {
        $groupName = strtoupper((string) ($result['status']['groupName'] ?? ''));
        $name = strtoupper((string) ($result['status']['name'] ?? ''));

        if ($groupName === '' && $name === '') {
            return null;
        }

        if ($groupName === 'DELIVERED' || str_contains($name, 'DELIVERED')) {
            return 'delivered';
        }

        if ($groupName === 'UNDELIVERABLE' || str_contains($name, 'REJECTED') || str_contains($name, 'FAILED')) {
            return 'failed';
        }

        if ($groupName === 'RECEIVED') {
            return 'received';
        }

        return 'sent';
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
                'provider_message_id' => (string) Str::uuid(),
                'logs' => $exception->response?->body(),
            ]);

            Log::error('Infobip Error: '.$exception->response?->body());
            throw $exception;
        }

        $responseBody = $response->json() ?? [];
        $firstResult = is_array($responseBody['messages'][0] ?? null) ? $responseBody['messages'][0] : [];
        $providerMessageId = (string) ($responseBody['messages'][0]['messageId'] ?? Str::uuid());
        $resolvedStatus = $this->mapWebhookStatusToLocal($firstResult) ?? 'sent';

        Message::create([
            'from_number_id' => $fromNumber->id,
            'to_number_id' => $toNumber->id,
            'provider_id' => $provider->id,
            'direction' => 'outgoing',
            'message_text' => '[TEMPLATE: '.$templateName.'] '.implode(' | ', $placeholders),
            'status' => $resolvedStatus,
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
            'logs' => json_encode($responseBody),
        ]);

        return $responseBody;
    }

    public function sendSms(string $from, string $to, string $text): array
    {
        return $this->sendTextMessage($from, $to, $text);
    }
}
