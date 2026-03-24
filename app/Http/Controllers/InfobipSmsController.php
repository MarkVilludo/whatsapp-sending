<?php

namespace App\Http\Controllers;

use App\Services\InfobipSmsService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InfobipSmsController extends Controller
{

    public function send(Request $request, InfobipSmsService $service): JsonResponse
    {
        $payload = $request->all();

        if (isset($payload['messages'][0]) && is_array($payload['messages'][0])) {
            $message = $payload['messages'][0];
            $from = (string) ($message['from'] ?? '');
            $to = (string) ($message['to'] ?? '');
            $content = is_array($message['content'] ?? null) ? $message['content'] : [];

            if ($from === '' || $to === '') {
                return response()->json([
                    'message' => 'Validation error.',
                    'error' => 'messages.0.from and messages.0.to are required.',
                ], 422);
            }

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
                        return response()->json([
                            'message' => 'Validation error.',
                            'error' => 'messages.0.content.templateName is required for template sends.',
                        ], 422);
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
                return response()->json([
                    'message' => 'Failed to send WhatsApp message.',
                    'error' => $exception->response?->json() ?? $exception->getMessage(),
                ], 502);
            }

            return response()->json([
                'message' => 'Message sent successfully.',
                'data' => $response,
            ]);
        }

        $validated = $request->validate([
            'from' => ['required', 'string', 'max:20'],
            'to' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:500'],
        ]);

        try {
            $response = $service->sendTextMessage(
                $validated['from'],
                $validated['to'],
                $validated['message']
            );
        } catch (RequestException $exception) {
            return response()->json([
                'message' => 'Failed to send WhatsApp text message.',
                'error' => $exception->response?->json() ?? $exception->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => $response,
        ]);
    }

    public function receive(Request $request, InfobipSmsService $service): JsonResponse
    {
        $service->handleIncomingWebhook($request->all());

        return response()->json([
            'message' => 'Webhook payload processed.',
        ]);
    }
}
