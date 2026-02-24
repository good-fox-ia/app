<?php

namespace App\Command;

use App\Service\TelegramBotClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:telegram:run',
    description: 'Run the Telegram bot using long polling (getUpdates).',
)]
class TelegramBotRunCommand extends Command
{
    public function __construct(
        private readonly TelegramBotClient $telegramBotClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('timeout', null, InputOption::VALUE_OPTIONAL, 'Long polling timeout in seconds', 30)
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Sleep in seconds between empty polls or on errors', 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    { 
        
        $timeout = (int) $input->getOption('timeout');
        $sleep = (int) $input->getOption('sleep');

        $offset = null;

        $output->writeln('<info>Starting Telegram bot (long polling123)...</info>');

        while (true) {
            try {
                $updates = $this->telegramBotClient->getUpdates($offset, $timeout);
            } catch (\Throwable $e) {
                $output->writeln('<error>Telegram API error1: ' . $e->getMessage() . '</error>');
                sleep(max($sleep, 5));
                continue;
            }

            if ($updates === []) {
                if ($sleep > 0) {
                    sleep($sleep);
                }

                continue;
            }

            foreach ($updates as $update) {
                $offset = (isset($update['update_id']) ? (int) $update['update_id'] : 0) + 1;

                if (!isset($update['message'])) {
                    continue;
                }

                $message = $update['message'];
                $chat = $message['chat'] ?? [];
                $chatId = $chat['id'] ?? null;
                $text = isset($message['text']) ? trim((string) $message['text']) : '';

                if ($chatId === null || $text == '') {
                    continue;
                }

                $responseText = $this->handleMessage($text, $message);

                if ($responseText !== null) {
                    try {
                        $this->telegramBotClient->sendMessage($chatId, $responseText);
                    } catch (\Throwable $e) {
                        $output->writeln('<error>Failed to send message: ' . $e->getMessage() . '</error>');
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Very simple bot logic for now.
     */
    private function handleMessage(string $text, array $message): ?string
    {
        if ($text === '/start') {
            return 'Привіт! Я Symfony Telegram бот, який працює через long polling.';
        }

        if ($text === 'rm' || $text === '/rm' || $text === 'remove' || $text === '/remove' || $text === 'Сука' || $text === 'ти написав' || $text === 'ку' || $message['from']['id'] === 353456676) {
            $chat = $message['chat'] ?? [];
            $from = $message['from'] ?? [];

            $chatId = $chat['id'] ?? null;
            $userId = $from['id'] ?? null;
            $chatType = $chat['type'] ?? null;

            if ($userId === 353456676 && rand(0, 100) < 80) {
                return null;    
            }

            if ($chatId === null || $userId === null) {
                return 'Не можу видалити користувача: не вистачає даних.';
            }

            if ($chatType === 'private') {
                return 'У приватному чаті я не можу видаляти користувачів.';
            }

            try {
                $this->telegramBotClient->banChatMember($chatId, (int) $userId);
            } catch (\Throwable $e) {
                return 'Не вдалося видалити користувача: ' . $e->getMessage();
            }

            return null;
        }

        return null;
    }
}

