<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Minimal Claude (Anthropic Messages API) client for JSON-structured generation.
 */
class Claude
{
    /**
     * @param  array<string, mixed>  $requestOptions  SDK request options (tests inject a fake HTTP transporter here).
     */
    public function __construct(private array $requestOptions = []) {}

    /**
     * Ask the model for a JSON answer matching the given schema.
     *
     * @param  array<string, mixed>  $schema  JSON Schema of the answer (root must be an object).
     * @return array<mixed> The decoded JSON.
     *
     * @throws RuntimeException when the API key is missing
     * @throws APIException when the API call fails
     * @throws UnexpectedValueException when the model refuses or the answer is not valid JSON
     */
    public function generateJson(string $prompt, array $schema): array
    {
        $key = config('services.anthropic.key');

        if (! $key) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $message = (new Client(apiKey: $key, requestOptions: $this->requestOptions))->messages->create(
            model: config('services.anthropic.model'),
            // Thinking tokens count towards this limit, so leave plenty of room for the JSON.
            maxTokens: 16000,
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
            messages: [['role' => 'user', 'content' => $prompt]],
        );

        if ($message->stopReason === 'refusal') {
            throw new UnexpectedValueException('Claude declined the request: '.($message->stopDetails->category ?? 'unknown'));
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                if (is_array($data)) {
                    return $data;
                }
            }
        }

        throw new UnexpectedValueException('Claude returned no valid JSON (stop reason: '.($message->stopReason ?? 'unknown').').');
    }
}
