<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Middleware;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageUpdater;
use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Shopware\Core\Framework\MessageQueue\Stamp\DecryptedStamp;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class RetryMiddleware implements MiddlewareInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $deadMessageRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var DeadMessageUpdater
     */
    private $deadMessageUpdater;

    public function __construct(EntityRepositoryInterface $deadMessageRepository, DeadMessageUpdater $deadMessageUpdater)
    {
        $this->deadMessageRepository = $deadMessageRepository;
        $this->deadMessageUpdater = $deadMessageUpdater;
        $this->context = Context::createDefaultContext();
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (MessageFailedException $e) {
            $deadMessage = null;
            if ($envelope->getMessage() instanceof RetryMessage) {
                /** @var DeadMessageEntity|null $deadMessage */
                $deadMessage = $this->deadMessageRepository
                    ->search(new Criteria([$envelope->getMessage()->getDeadMessageId()]), $this->context)
                    ->get($envelope->getMessage()->getDeadMessageId());
            }

            if ($deadMessage) {
                $this->handleExistingDeadMessage($deadMessage, $e);

                return $envelope;
            }

            $this->createDeadMessageFromEnvelope($envelope, $e);
        }

        return $envelope;
    }

    private function createDeadMessageFromEnvelope(Envelope $envelope, MessageFailedException $e): void
    {
        $encrypted = count($envelope->all(DecryptedStamp::class)) > 0;
        $scheduledTaskId = null;
        if ($envelope->getMessage() instanceof ScheduledTaskInterface) {
            $scheduledTaskId = $envelope->getMessage()->getTaskId();
        }

        $id = Uuid::uuid4()->getHex();
        $this->deadMessageRepository->create([
            [
                'id' => $id,
                'originalMessageClass' => get_class($envelope->getMessage()),
                'handlerClass' => $e->getHandlerClass(),
                'encrypted' => $encrypted,
                'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime(1),
                'exception' => get_class($e->getPrevious()),
                'exceptionMessage' => $e->getPrevious()->getMessage(),
                'exceptionFile' => $e->getPrevious()->getFile(),
                'exceptionLine' => $e->getPrevious()->getLine(),
                'scheduledTaskId' => $scheduledTaskId,
            ],
        ], $this->context);
        $this->deadMessageUpdater->updateOriginalMessage($id, $envelope->getMessage());
    }

    private function handleExistingDeadMessage(DeadMessageEntity $deadMessage, MessageFailedException $e): void
    {
        if ($this->isExceptionEqual($deadMessage, $e->getPrevious())) {
            $this->incrementErrorCount($deadMessage);

            return;
        }

        $this->deadMessageRepository->delete([
            [
                'id' => $deadMessage->getId(),
            ],
        ], $this->context);
        $this->createDeadMessageFromExistingMessage($deadMessage, $e);
    }

    private function isExceptionEqual(DeadMessageEntity $deadMessage, \Throwable $e): bool
    {
        return $deadMessage->getException() === get_class($e) &&
            $deadMessage->getExceptionMessage() === $e->getMessage() &&
            $deadMessage->getExceptionFile() === $e->getFile() &&
            $deadMessage->getExceptionLine() === $e->getLine();
    }

    private function incrementErrorCount(DeadMessageEntity $deadMessage): void
    {
        $this->deadMessageRepository->update([
            [
                'id' => $deadMessage->getId(),
                'errorCount' => $deadMessage->getErrorCount() + 1,
                'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime($deadMessage->getErrorCount() + 1),
            ],
        ], $this->context);
    }

    private function createDeadMessageFromExistingMessage(DeadMessageEntity $message, MessageFailedException $e): void
    {
        $id = Uuid::uuid4()->getHex();
        $this->deadMessageRepository->create([
            [
                'id' => $id,
                'originalMessageClass' => $message->getOriginalMessageClass(),
                'handlerClass' => $e->getHandlerClass(),
                'encrypted' => $message->isEncrypted(),
                'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime(1),
                'exception' => get_class($e->getPrevious()),
                'exceptionMessage' => $e->getPrevious()->getMessage(),
                'exceptionFile' => $e->getPrevious()->getFile(),
                'exceptionLine' => $e->getPrevious()->getLine(),
            ],
        ], $this->context);
        $this->deadMessageUpdater->updateOriginalMessage($id, $message->getOriginalMessage());
    }
}
