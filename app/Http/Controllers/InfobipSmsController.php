<?php

namespace App\Http\Controllers;

use App\DTOs\ApiResponseDto;
use App\Http\Requests\InfobipSendMessageRequest;
use App\Services\InfobipSmsService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfobipSmsController extends Controller
{

    public function send(InfobipSendMessageRequest $request, InfobipSmsService $service): JsonResponse
    {
        $request->validated();

        if ($request->hasStructuredPayload()) {
            $message = $request->structuredMessage();
            $from = (string) ($message['from'] ?? '');
            $to = (string) ($message['to'] ?? '');
            $content = is_array($message['content'] ?? null) ? $message['content'] : [];

            try {
                if (isset($content['text'])) {
                    $response = $service->sendTextMessage(
                        $from,
                        $to,
                        (string) $content['text']
                    );
                } else {
                    $templateName = (string) ($content['templateName'] ?? ($content['templateId'] ?? ''));
                    $language = (string) ($content['language'] ?? 'en');
                    $components = is_array($content['components'] ?? null) ? $content['components'] : [];
                    $placeholders = [];
                    $templateData = is_array($content['templateData'] ?? null) ? $content['templateData'] : null;

                    if ($templateData === null) {
                        foreach ($components as $component) {
                            if (! is_array($component)) {
                                continue;
                            }

                            if (($component['type'] ?? null) === 'body' && isset($component['parameters']) && is_array($component['parameters'])) {
                                foreach ($component['parameters'] as $parameter) {
                                    if (is_array($parameter) && isset($parameter['text'])) {
                                        $placeholders[] = (string) $parameter['text'];
                                    }
                                }
                            }
                        }
                    }

                    if ($templateName === '') {
                        $dto = ApiResponseDto::error(
                            'Validation error.',
                            'messages.0.content.templateName is required for template sends.'
                        );

                        return response()->json($dto->toArray(), 422);
                    }

                    $response = $service->sendTemplateMessage(
                        $from,
                        $to,
                        $templateName,
                        $placeholders,
                        $language,
                        $templateData
                    );
                }
            } catch (RequestException $exception) {
                $dto = ApiResponseDto::error(
                    'Failed to send WhatsApp message.',
                    $exception->response?->json() ?? $exception->getMessage()
                );

                return response()->json($dto->toArray(), 502);
            }

            $dto = ApiResponseDto::success('Message sent successfully.', $response);

            return response()->json($dto->toArray());
        }

        if ($request->hasFlatContentPayload()) {
            $from = (string) $request->input('from', '');
            $to = (string) $request->input('to', '');
            $content = $request->flatContentPayload();

            try {
                if (($content['type'] ?? null) === 'text' || isset($content['text'])) {
                    $response = $service->sendTextMessage(
                        $from,
                        $to,
                        (string) ($content['text'] ?? '')
                    );
                } else {
                    $templateName = (string) ($content['templateName'] ?? ($content['templateId'] ?? ''));
                    $language = (string) ($content['language'] ?? 'en');
                    $templateData = is_array($content['templateData'] ?? null) ? $content['templateData'] : null;

                    $placeholders = [];
                    if (isset($templateData['body']['placeholders']) && is_array($templateData['body']['placeholders'])) {
                        $placeholders = array_values(array_map('strval', $templateData['body']['placeholders']));
                    }

                    $response = $service->sendTemplateMessage(
                        $from,
                        $to,
                        $templateName,
                        $placeholders,
                        $language,
                        $templateData
                    );
                }
            } catch (RequestException $exception) {
                $dto = ApiResponseDto::error(
                    'Failed to send WhatsApp message.',
                    $exception->response?->json() ?? $exception->getMessage()
                );

                return response()->json($dto->toArray(), 502);
            }

            $dto = ApiResponseDto::success('Message sent successfully.', $response);

            return response()->json($dto->toArray());
        }

        $validated = $request->simplePayload();

        try {
            $response = $service->sendTextMessage(
                $validated['from'],
                $validated['to'],
                $validated['message']
            );
        } catch (RequestException $exception) {
            $dto = ApiResponseDto::error(
                'Failed to send WhatsApp text message.',
                $exception->response?->json() ?? $exception->getMessage()
            );

            return response()->json($dto->toArray(), 502);
        }

        $dto = ApiResponseDto::success('Message sent successfully.', $response);

        return response()->json($dto->toArray());
    }

    public function receive(Request $request, InfobipSmsService $service): JsonResponse
    {
        $service->handleIncomingWebhook($request->all());

        $dto = ApiResponseDto::success('Webhook payload processed.');

        return response()->json($dto->toArray());
    }
}
