<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Handler;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;

class RetryMessageHandler extends AbstractMessageHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    private $deadMessageRepository;

    /**
     * @var iterable|AbstractMessageHandler[]
     */
    private $handler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        EntityRepositoryInterface $deadMessageRepository,
        iterable $handler,
        LoggerInterface $logger
    ) {
        $this->deadMessageRepository = $deadMessageRepository;
        $this->handler = $handler;
        $this->logger = $logger;
    }

    /**
     * @param RetryMessage $message
     */
    public function handle($message): void
    {
        /** @var DeadMessageEntity|null $deadMessage */
        $deadMessage = $this->deadMessageRepository
            ->search(new Criteria([$message->getDeadMessageId()]), Context::createDefaultContext())
            ->get($message->getDeadMessageId());

        if (!$deadMessage) {
            return;
        }

        $handler = $this->findHandler($deadMessage->getHandlerClass());

        if ($handler) {
            $handler($deadMessage->getOriginalMessage());
        }

        $this->deadMessageRepository->delete([
            [
                'id' => $deadMessage->getId(),
            ],
        ], Context::createDefaultContext());
    }

    public static function getHandledMessages(): iterable
    {
        return [RetryMessage::class];
    }

    private function findHandler(string $handlerClass): ?AbstractMessageHandler
    {
        foreach ($this->handler as $handler) {
            if (get_class($handler) === $handlerClass) {
                return $handler;
            }
        }

        $this->logger->warning(sprintf('MessageHandler for class "%s" not found.', $handlerClass));

        return null;
    }
}
