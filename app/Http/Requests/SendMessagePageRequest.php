<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessagePageRequest extends FormRequest
{
    private const E164_REGEX = '/^\+?[1-9]\d{7,14}$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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
            'from' => ['required', 'string', 'max:20', 'regex:'.self::E164_REGEX],
            'to' => ['required', 'string', 'max:20', 'regex:'.self::E164_REGEX],
            'message' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.regex' => 'The from number must be a valid international number (e.g. 639954456403).',
            'to.regex' => 'The to number must be a valid international number (e.g. 639954456403).',
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
