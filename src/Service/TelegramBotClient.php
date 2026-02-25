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

    public function banChatMember(int|string $chatId, int $userId): void
    {
        $response = $this->httpClient->request('POST', $this->apiBaseUrl . 'banChatMember', [
            'body' => [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ],
        ]);

        $data = $response->toArray(false);

        if (!isset($data['ok']) || $data['ok'] !== true) {
            throw new \RuntimeException('Telegram API error in banChatMember: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getFile(string $fileId): array
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl . 'getFile', [
            'query' => [
                'file_id' => $fileId,
            ],
        ]);

        $data = $response->toArray(false);

        if (!isset($data['ok']) || $data['ok'] !== true || !isset($data['result'])) {
            throw new \RuntimeException('Telegram API error in getFile: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        /** @var array<string, mixed> $result */
        $result = $data['result'];

        return $result;
    }

    public function downloadFile(string $filePath): string
    {
        $fileUrl = sprintf(
            'https://api.telegram.org/file/bot%s/%s',
            $this->telegramBotToken,
            ltrim($filePath, '/'),
        );

        $response = $this->httpClient->request('GET', $fileUrl);

        return $response->getContent(false);
    }

    public function sendMessage(int|string $chatId, string $text, ?int $replyToMessageId = null): void
    {
        $body = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyToMessageId !== null) {
            $body['reply_to_message_id'] = $replyToMessageId;
            $body['allow_sending_without_reply'] = true;
        }

        $response = $this->httpClient->request('POST', $this->apiBaseUrl . 'sendMessage', [
            'body' => $body,
        ]);

        $data = $response->toArray(false);

        if (!isset($data['ok']) || $data['ok'] !== true) {
            throw new \RuntimeException('Telegram API error in sendMessage: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }
}

