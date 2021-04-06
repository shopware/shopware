<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Handler;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\MessageQueue\Message\SleepMessage;

class SleepTaskHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(SleepMessage $message): void
    {
        $this->logger->info(
            'Start sleeping for {seconds} seconds',
            ['seconds' => $message->getSleepTime()]
        );

        usleep((int) ($message->getSleepTime() * 1000000));

        $this->logger->info(
            'Stopped sleeping for {seconds} seconds',
            ['seconds' => $message->getSleepTime()]
        );

        if ($message->isThrowError()) {
            throw new \RuntimeException(self::class . ' error');
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [SleepMessage::class];
    }
}
