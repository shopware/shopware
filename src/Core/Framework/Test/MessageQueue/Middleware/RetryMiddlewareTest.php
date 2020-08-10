<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Middleware;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Shopware\Core\Framework\MessageQueue\Handler\RetryMessageHandler;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Shopware\Core\Framework\MessageQueue\Middleware\RetryMiddleware;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\Stamp\DecryptedStamp;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\DummyHandler;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class RetryMiddlewareTest extends MiddlewareTestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $deadMessageRepository;

    /**
     * @var RetryMiddleware
     */
    private $retryMiddleware;

    /**
     * @var Context
     */
    private $context;

    public function setUp(): void
    {
        $this->deadMessageRepository = $this->getContainer()->get('dead_message.repository');
        $this->retryMiddleware = $this->getContainer()->get(RetryMiddleware::class);
        $this->context = Context::createDefaultContext();
    }

    public function testMiddlewareOnSuccess(): void
    {
        $message = new TestMessage(Uuid::randomHex());
        $envelope = new Envelope($message);

        $stack = $this->getStackMock();

        $this->retryMiddleware->handle($envelope, $stack);

        $deadMessages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(0, $deadMessages);
    }

    public function testMiddlewareOnFirstError(): void
    {
        $message = new TestMessage(Uuid::randomHex());
        $envelope = new Envelope($message);

        $e = new \Exception('exception');
        $messageFailedException = $this->getMessageFailedException($envelope, $message, $e);
        $stack = $this->getThrowingStackMock($messageFailedException);

        $this->retryMiddleware->handle($envelope, $stack);

        $deadMessages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $deadMessages);
        /** @var DeadMessageEntity $deadMessage */
        $deadMessage = $deadMessages->first();
        $this->assertDeadMessageCombinesExceptionAndMessage($deadMessage, $e, $message, 1);
    }

    public function testMiddlewareMultipleFailedHandlers(): void
    {
        $message = new TestMessage(Uuid::randomHex());
        $envelope = new Envelope($message);

        $e = new \Exception('exception');
        $messageFailedException = $this->getMultiMessageFailedException($envelope, $message, $e);
        $stack = $this->getThrowingStackMock($messageFailedException);

        $this->retryMiddleware->handle($envelope, $stack);

        $deadMessages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(2, $deadMessages);
        /** @var DeadMessageEntity $deadMessage */
        $deadMessage = $deadMessages->first();
        $this->assertDeadMessageCombinesExceptionAndMessage($deadMessage, $e, $message, 1);
    }

    public function testMixedExceptions(): void
    {
        $message = new TestMessage(Uuid::randomHex());
        $envelope = new Envelope($message);

        $e = new \Exception('exception');
        $exception1 = new MessageFailedException($message, RetryMessageHandler::class, $e);
        $exception2 = new \Exception('exception2');

        $messageFailedException = new HandlerFailedException($envelope, [$exception1, $exception2]);
        $stack = $this->getThrowingStackMock($messageFailedException);

        $thrown = false;

        try {
            $this->retryMiddleware->handle($envelope, $stack);
        } catch (HandlerFailedException $err) {
            $thrown = true;
            static::assertCount(1, $err->getNestedExceptions());
            static::assertEquals($exception2, $err->getNestedExceptions()[0]);
        }
        static::assertTrue($thrown);

        $deadMessages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $deadMessages);
        /** @var DeadMessageEntity $deadMessage */
        $deadMessage = $deadMessages->first();
        $this->assertDeadMessageCombinesExceptionAndMessage($deadMessage, $e, $message, 1);
    }

    public function testMiddlewareSavesScheduledTask(): void
    {
        $taskId = Uuid::randomHex();
        $message = $this->createMock(ScheduledTask::class);
        $message->method('getTaskId')
            ->willReturn($taskId);
        $envelope = new Envelope($message);

        $scheduledTaskRepo = $this->getContainer()->get('scheduled_task.repository');
        $scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => get_class($message),
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
            ],
        ], $this->context);

        $e = new \Exception('exception');
        $messageFailedException = $this->getMessageFailedException($envelope, $message, $e);
        $stack = $this->getThrowingStackMock($messageFailedException);

        $this->retryMiddleware->handle($envelope, $stack);

        $deadMessages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $deadMessages);
        /** @var DeadMessageEntity $deadMessage */
        $deadMessage = $deadMessages->first();
        $this->assertDeadMessageCombinesExceptionAndMessage($deadMessage, $e, $message, 1);
        static::assertEquals($taskId, $deadMessage->getScheduledTaskId());
    }

    public function testMiddlewareWithEncryptedMessage(): void
    {
        $message = new TestMessage(Uuid::randomHex());
        $envelope = new Envelope($message, [new DecryptedStamp()]);

        $e = new \Exception('exception');
        $messageFailedException = $this->getMessageFailedException($envelope, $message, $e);
        $stack = $this->getThrowingStackMock($messageFailedException);

        $this->retryMiddleware->handle($envelope, $stack);

        $deadMessages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $deadMessages);
        /** @var DeadMessageEntity $deadMessage */
        $deadMessage = $deadMessages->first();
        static::assertInstanceOf(DeadMessageEntity::class, $deadMessage);
        static::assertTrue($deadMessage->isEncrypted());
    }

    public function testMiddlewareOnConsecutiveError(): void
    {
        $deadMessageId = Uuid::randomHex();
        $message = new RetryMessage($deadMessageId);
        $envelope = new Envelope($message);

        $e = new \Exception('exception');
        $messageFailedException = $this->getMessageFailedException($envelope, $message, $e);
        $stack = $this->getThrowingStackMock($messageFailedException);

        $this->insertDeadMessage($deadMessageId, $envelope, $e);

        $this->retryMiddleware->handle($envelope, $stack);

        $deadMessages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $deadMessages);
        /** @var DeadMessageEntity $deadMessage */
        $deadMessage = $deadMessages->first();
        $this->assertDeadMessageCombinesExceptionAndMessage($deadMessage, $e, $message, 2);
        static::assertEquals($deadMessageId, $deadMessage->getId());
        static::assertFalse($deadMessage->isEncrypted());
    }

    public function testMiddlewareOnDifferentError(): void
    {
        $deadMessageId = Uuid::randomHex();
        $message = new RetryMessage($deadMessageId);
        $envelope = new Envelope($message);

        $previousException = new \Exception('exception');

        $newException = new \RuntimeException('runtime exception');
        $messageFailedException = $this->getMessageFailedException($envelope, $message, $newException);
        $stack = $this->getThrowingStackMock($messageFailedException);

        $this->insertDeadMessage($deadMessageId, $envelope, $previousException);

        $this->retryMiddleware->handle($envelope, $stack);

        $deadMessages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $deadMessages);
        /** @var DeadMessageEntity $deadMessage */
        $deadMessage = $deadMessages->first();
        static::assertInstanceOf(DeadMessageEntity::class, $deadMessage);
        $this->assertDeadMessageCombinesExceptionAndMessage($deadMessage, $newException, $message, 1);
        static::assertNotEquals($deadMessageId, $deadMessage->getId());
        static::assertFalse($deadMessage->isEncrypted());
    }

    private function assertDeadMessageCombinesExceptionAndMessage(
        DeadMessageEntity $deadMessage,
        \Exception $e,
        object $message,
        int $errorCount
    ): void {
        static::assertInstanceOf(DeadMessageEntity::class, $deadMessage);
        static::assertEquals(get_class($e), $deadMessage->getException());
        static::assertEquals(get_class($message), $deadMessage->getOriginalMessageClass());
        static::assertEquals($message, $deadMessage->getOriginalMessage());
        static::assertEquals($e->getMessage(), $deadMessage->getExceptionMessage());
        static::assertEquals($e->getFile(), $deadMessage->getExceptionFile());
        static::assertEquals($e->getLine(), $deadMessage->getExceptionLine());
        static::assertTrue(new \DateTime() < $deadMessage->getNextExecutionTime());
        static::assertFalse($deadMessage->isEncrypted());
        static::assertEquals($errorCount, $deadMessage->getErrorCount());
    }

    private function insertDeadMessage(string $deadMessageId, Envelope $envelope, \Exception $e): void
    {
        $this->deadMessageRepository->create([
            [
                'id' => $deadMessageId,
                'originalMessageClass' => get_class($envelope->getMessage()),
                'serializedOriginalMessage' => serialize($envelope->getMessage()),
                'handlerClass' => RetryMessageHandler::class,
                'encrypted' => false,
                'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime(1),
                'exception' => get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
            ],
        ], $this->context);
    }

    private function getMessageFailedException(Envelope $env, $message, \Exception $e): HandlerFailedException
    {
        $exception = new MessageFailedException($message, RetryMessageHandler::class, $e);

        return new HandlerFailedException($env, [$exception]);
    }

    private function getMultiMessageFailedException(Envelope $env, TestMessage $message, \Exception $e): HandlerFailedException
    {
        $exception1 = new MessageFailedException($message, RetryMessageHandler::class, $e);
        $exception2 = new MessageFailedException($message, DummyHandler::class, $e);

        return new HandlerFailedException($env, [$exception1, $exception2]);
    }
}
