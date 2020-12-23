<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Middleware;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\Stamp\DecryptedStamp;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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

    public function __construct(EntityRepositoryInterface $deadMessageRepository)
    {
        $this->deadMessageRepository = $deadMessageRepository;
        $this->context = Context::createDefaultContext();
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $e) {
            $deadMessage = $this->getExistingDeadMessage($envelope);

            $unhandledExceptions = [];
            foreach ($e->getNestedExceptions() as $nestedException) {
                if (!($nestedException instanceof MessageFailedException)) {
                    $unhandledExceptions[] = $nestedException;

                    continue;
                }
                if ($deadMessage) {
                    $this->handleExistingDeadMessage($deadMessage, $nestedException);
                } else {
                    $this->createDeadMessageFromEnvelope($envelope, $nestedException);
                }
            }

            if (\count($unhandledExceptions) > 0) {
                throw new HandlerFailedException($envelope, $unhandledExceptions);
            }
        }

        return $envelope;
    }

    private function createDeadMessageFromEnvelope(Envelope $envelope, MessageFailedException $e): void
    {
        $this->context->scope(Context::SYSTEM_SCOPE, function () use ($envelope, $e): void {
            $encrypted = \count($envelope->all(DecryptedStamp::class)) > 0;
            $scheduledTaskId = null;
            if ($envelope->getMessage() instanceof ScheduledTask) {
                $scheduledTaskId = $envelope->getMessage()->getTaskId();
            }

            $id = Uuid::randomHex();

            $params = [
                'id' => $id,
                'originalMessageClass' => \get_class($envelope->getMessage()),
                'serializedOriginalMessage' => serialize($envelope->getMessage()),
                'handlerClass' => $e->getHandlerClass(),
                'encrypted' => $encrypted,
                'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime(1),
                'exception' => \get_class($e->getException()),
                'exceptionMessage' => $e->getException()->getMessage(),
                'exceptionFile' => $e->getException()->getFile(),
                'exceptionLine' => $e->getException()->getLine(),
                'scheduledTaskId' => $scheduledTaskId,
            ];

            try {
                $this->deadMessageRepository->create([$params], $this->context);
            } catch (\Throwable $e) {
                $params['exceptionMessage'] = ' ';
                $this->deadMessageRepository->create([$params], $this->context);
            }
        });
    }

    private function handleExistingDeadMessage(DeadMessageEntity $deadMessage, MessageFailedException $e): void
    {
        if ($this->isExceptionEqual($deadMessage, $e->getException())) {
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
        return $deadMessage->getException() === \get_class($e)
            && $deadMessage->getExceptionMessage() === $e->getMessage()
            && $deadMessage->getExceptionFile() === $e->getFile()
            && $deadMessage->getExceptionLine() === $e->getLine();
    }

    private function incrementErrorCount(DeadMessageEntity $deadMessage): void
    {
        $this->context->scope(Context::SYSTEM_SCOPE, function () use ($deadMessage): void {
            $this->deadMessageRepository->update([
                [
                    'id' => $deadMessage->getId(),
                    'errorCount' => $deadMessage->getErrorCount() + 1,
                    'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime($deadMessage->getErrorCount() + 1),
                ],
            ], $this->context);
        });
    }

    private function createDeadMessageFromExistingMessage(DeadMessageEntity $message, MessageFailedException $e): void
    {
        $this->context->scope(Context::SYSTEM_SCOPE, function () use ($message, $e): void {
            $id = Uuid::randomHex();
            $this->deadMessageRepository->create([
                [
                    'id' => $id,
                    'originalMessageClass' => $message->getOriginalMessageClass(),
                    'serializedOriginalMessage' => serialize($message->getOriginalMessage()),
                    'handlerClass' => $e->getHandlerClass(),
                    'encrypted' => $message->isEncrypted(),
                    'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime(1),
                    'exception' => \get_class($e->getException()),
                    'exceptionMessage' => $e->getException()->getMessage(),
                    'exceptionFile' => $e->getException()->getFile(),
                    'exceptionLine' => $e->getException()->getLine(),
                ],
            ], $this->context);
        });
    }

    private function getExistingDeadMessage(Envelope $envelope): ?DeadMessageEntity
    {
        if (!($envelope->getMessage() instanceof RetryMessage)) {
            return null;
        }
        /** @var DeadMessageEntity|null $deadMessage */
        $deadMessage = $this->deadMessageRepository
            ->search(new Criteria([$envelope->getMessage()->getDeadMessageId()]), $this->context)
            ->get($envelope->getMessage()->getDeadMessageId());

        return $deadMessage;
    }
}
