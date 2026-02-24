<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramBotClient
{
    private string $apiBaseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $telegramBotToken,
    ) {
        $this->apiBaseUrl = sprintf('https://api.telegram.org/bot%s/', $this->telegramBotToken);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(?int $offset = null, int $timeout = 30): array
    {
        $params = [
            'timeout' => $timeout,
        ];

        if ($offset !== null) {
            $params['offset'] = $offset;
        }

        $response = $this->httpClient->request('GET', $this->apiBaseUrl . 'getUpdates', [
            'query' => $params,
        ]);

        $data = $response->toArray(false);

        if (!isset($data['ok']) || $data['ok'] !== true) {
            throw new \RuntimeException('Telegram API error in get1Updates: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . ' URL: ' . $this->apiBaseUrl . 'getUpdates');
        }

        /** @var array<int, array<string, mixed>> $result */
        $result = $data['result'] ?? [];

        return $result;
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        $response = $this->httpClient->request('POST', $this->apiBaseUrl . 'sendMessage', [
            'body' => [
                'chat_id' => $chatId,
                'text' => $text,
            ],
        ]);

        $data = $response->toArray(false);

        if (!isset($data['ok']) || $data['ok'] !== true) {
            throw new \RuntimeException('Telegram API error in sendMessage: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }
}

