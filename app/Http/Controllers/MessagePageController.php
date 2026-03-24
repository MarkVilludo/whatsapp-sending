<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessagePageRequest;
use App\Models\Message;
use App\Services\InfobipSmsService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MessagePageController extends Controller
{
    public function index(): View
    {
        $incomingMessages = Message::with(['fromNumber', 'toNumber', 'provider'])
            ->where('direction', 'incoming')
            ->latest()
            ->get();

        $outgoingMessages = Message::with(['fromNumber', 'toNumber', 'provider'])
            ->where('direction', 'outgoing')
            ->latest()
            ->get();

        return view('messages.index', [
            'incomingMessages' => $incomingMessages,
            'outgoingMessages' => $outgoingMessages,
        ]);
    }

    public function send(SendMessagePageRequest $request, InfobipSmsService $infobipSmsService): RedirectResponse
    {
        $validated = $request->validated();

        $templateData = [
            'body' => [
                'placeholders' => [$validated['message']],
            ],
        ];


        try {
            $infobipSmsService->sendTemplateMessage(
                $validated['from'],
                $validated['to'],
                'whatsapp_docs_quick_access',
                [$validated['message']],
                'en',
                $templateData
            );
        } catch (RequestException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'send' => $exception->response?->json()['requestError']['serviceException']['text']
                        ?? 'Failed to send message via Infobip.',
                ]);
        }

        return redirect()
            ->route('messages.index')
            ->with('status', 'Message request submitted to Infobip.');
    }
}
