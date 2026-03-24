<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InfobipSendMessageRequest extends FormRequest
{
    private const E164_REGEX = '/^\+?[1-9]\d{7,14}$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasStructuredPayload()) {
            $message = $this->structuredMessage();
            $message['from'] = $this->normalizePhone((string) ($message['from'] ?? ''));
            $message['to'] = $this->normalizePhone((string) ($message['to'] ?? ''));

            $messages = $this->input('messages', []);
            if (is_array($messages)) {
                $messages[0] = $message;
                $this->merge(['messages' => $messages]);
            }

            return;
        }

        $this->merge([
            'from' => $this->normalizePhone((string) $this->input('from', '')),
            'to' => $this->normalizePhone((string) $this->input('to', '')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'messages' => ['sometimes', 'array'],
            'messages.0' => ['sometimes', 'array'],
            'messages.0.from' => ['sometimes', 'string', 'max:20', 'regex:'.self::E164_REGEX],
            'messages.0.to' => ['sometimes', 'string', 'max:20', 'regex:'.self::E164_REGEX],
            'messages.0.content' => ['sometimes', 'array'],
            'from' => ['sometimes', 'string', 'max:20', 'regex:'.self::E164_REGEX],
            'to' => ['sometimes', 'string', 'max:20', 'regex:'.self::E164_REGEX],
            'message' => ['sometimes', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'messages.0.from.regex' => 'messages.0.from must be a valid international number (e.g. 639954456403).',
            'messages.0.to.regex' => 'messages.0.to must be a valid international number (e.g. 639954456403).',
            'from.regex' => 'from must be a valid international number (e.g. 639954456403).',
            'to.regex' => 'to must be a valid international number (e.g. 639954456403).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasStructuredPayload()) {
                $message = $this->structuredMessage();

                if (($message['from'] ?? '') === '' || ($message['to'] ?? '') === '') {
                    $validator->errors()->add('messages.0', 'messages.0.from and messages.0.to are required.');
                }

                return;
            }

            if (($this->input('from') ?? '') === '' || ($this->input('to') ?? '') === '' || ($this->input('message') ?? '') === '') {
                $validator->errors()->add('message', 'from, to, and message are required.');
            }
        });
    }

    public function hasStructuredPayload(): bool
    {
        $message = $this->input('messages.0');

        return is_array($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function structuredMessage(): array
    {
        $message = $this->input('messages.0', []);

        return is_array($message) ? $message : [];
    }

    /**
     * @return array{from:string,to:string,message:string}
     */
    public function simplePayload(): array
    {
        return [
            'from' => (string) $this->input('from', ''),
            'to' => (string) $this->input('to', ''),
            'message' => (string) $this->input('message', ''),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $trimmed = trim($phone);
        $normalized = (string) preg_replace('/[^\d+]/', '', $trimmed);

        // Keep original non-empty value so validation returns regex error, not required.
        return $normalized !== '' ? $normalized : $trimmed;
    }
}
