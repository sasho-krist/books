<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

/**
 * Minimal Gemini (Google AI Studio) client for JSON-structured generation.
 */
class Gemini
{
    /**
     * Ask the model for a JSON answer matching the given schema.
     *
     * @param  array<string, mixed>  $schema  Response schema (OpenAPI subset).
     * @return array<mixed> The decoded JSON.
     *
     * @throws RuntimeException when the API key is missing
     * @throws RequestException when the API call fails
     * @throws UnexpectedValueException when the answer is blocked or is not valid JSON
     */
    public function generateJson(string $prompt, array $schema): array
    {
        $key = config('services.gemini.key');

        if (! $key) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $response = Http::baseUrl(config('services.gemini.base_url'))
            ->withHeaders(['x-goog-api-key' => $key])
            ->acceptJson()
            ->timeout(60)
            ->retry(
                2,
                1500,
                fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            )
            ->post('/models/'.config('services.gemini.model').':generateContent', [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                    'temperature' => 0.8,
                ],
            ])
            ->throw();

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            $reason = $response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason') ?? 'no content';

            throw new UnexpectedValueException('Gemini returned no answer: '.$reason);
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new UnexpectedValueException('Gemini returned invalid JSON.');
        }

        return $data;
    }
}
