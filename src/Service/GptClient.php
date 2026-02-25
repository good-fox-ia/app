<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GptClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $geminiApiKey,
        private readonly string $geminiModel,
    ) {
    }

    public function ask(string $prompt, ?int $userId = null, ?string $imageData = null, ?string $imageMimeType = null): string
    {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            urlencode($this->geminiModel),
        );

        $lastException = null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $parts = [];

                if ($imageData !== null) {
                    $parts[] = [
                        'inline_data' => [
                            'data' => base64_encode($imageData),
                            'mime_type' => $imageMimeType ?: 'image/jpeg',
                        ],
                    ];
                }

                $parts[] = [
                    'text' => sprintf(
                        "You are a helpful assistant answering messages for a Telegram chat. Reply briefly and in the same language as the user if possible.\n\nUser ID: %s\nUser: %s",
                        $userId !== null ? (string) $userId : 'unknown',
                        $prompt,
                    ),
                ];

                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $this->geminiApiKey,
                    ],
                    'json' => [
                        'contents' => [
                            [
                                'parts' => $parts,
                            ],
                        ],
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                $data = $response->toArray(false);

                if ($statusCode === 503 || ($data['error']['code'] ?? null) === 503) {
                    if ($attempt === 0) {
                        sleep(1);
                        continue;
                    }

                    throw new \RuntimeException('Gemini API unavailable (503): ' . json_encode($data, JSON_UNESCAPED_UNICODE));
                }

                if (
                    !isset($data['candidates'][0]['content']['parts'][0]['text'])
                    || !is_string($data['candidates'][0]['content']['parts'][0]['text'])
                ) {
                    throw new \RuntimeException('Gemini API error: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
                }

                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt === 0) {
                    sleep(1);
                    continue;
                }

                break;
            }
        }

        throw ($lastException ?? new \RuntimeException('Unknown Gemini API error'));
    }
}

