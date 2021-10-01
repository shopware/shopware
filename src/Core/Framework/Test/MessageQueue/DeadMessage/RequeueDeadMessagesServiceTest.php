<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\DeadMessage;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\ScheduledTask\LogCleanupTask;
use Shopware\Core\Framework\MessageQueue\DeadMessage\RequeueDeadMessagesService;
use Shopware\Core\Framework\MessageQueue\Handler\EncryptedMessageHandler;
use Shopware\Core\Framework\MessageQueue\Message\EncryptedMessage;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class RequeueDeadMessagesServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $deadMessageRepository;

    private MessageBusInterface $bus;

    private MessageBusInterface $encryptedBus;

    private RequeueDeadMessagesService $requeueDeadMessageService;

    private LoggerInterface $logger;

    public function setUp(): void
    {
        $this->deadMessageRepository = $this->getContainer()->get('dead_message.repository');
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->encryptedBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(Logger::class);

        $this->requeueDeadMessageService = new RequeueDeadMessagesService(
            $this->deadMessageRepository,
            $this->bus,
            $this->encryptedBus,
            $this->logger
        );
    }

    public function testRequeueDeadMessages(): void
    {
        $msg = new EncryptedMessage('test');
        $e = new \Exception('exception');

        $encryptedId = Uuid::randomHex();
        $plainId = Uuid::randomHex();
        $futureId = Uuid::randomHex();

        $this->deadMessageRepository->create([
            [
                'id' => $encryptedId,
                'originalMessageClass' => EncryptedMessage::class,
                'serializedOriginalMessage' => serialize($msg),
                'handlerClass' => EncryptedMessageHandler::class,
                'encrypted' => true,
                'nextExecutionTime' => new \DateTime('2000-01-01'),
                'exception' => \get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
                'errorCount' => 3,
            ],
            [
                'id' => $plainId,
                'originalMessageClass' => EncryptedMessage::class,
                'serializedOriginalMessage' => serialize($msg),
                'handlerClass' => EncryptedMessageHandler::class,
                'encrypted' => false,
                'nextExecutionTime' => new \DateTime('2000-01-01'),
                'exception' => \get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
            ],
            [
                'id' => $futureId,
                'originalMessageClass' => EncryptedMessage::class,
                'serializedOriginalMessage' => serialize($msg),
                'handlerClass' => EncryptedMessageHandler::class,
                'encrypted' => false,
                'nextExecutionTime' => (new \DateTime())->modify('+1 day'),
                'exception' => \get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
            ],
        ], Context::createDefaultContext());

        $this->bus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (RetryMessage $message) use ($plainId) {
                static::assertEquals($plainId, $message->getDeadMessageId());

                return true;
            }))
            ->willReturn(new Envelope(new RetryMessage($plainId)));

        $this->encryptedBus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (RetryMessage $message) use ($encryptedId) {
                static::assertEquals($encryptedId, $message->getDeadMessageId());

                return true;
            }))
            ->willReturn(new Envelope(new RetryMessage($encryptedId)));

        $this->requeueDeadMessageService->requeue();
    }

    public function testDoNotRequeueNotFoundDeadMessages(): void
    {
        $msg = new EncryptedMessage('test');
        $e = new \Exception('exception');

        $encryptedId = Uuid::randomHex();

        $this->deadMessageRepository->create([
            [
                'id' => $encryptedId,
                'originalMessageClass' => '\Shopware\Core\Framework\MessageQueue\DeadMessage\AMessageThatDoesNotExist',
                'serializedOriginalMessage' => serialize($msg),
                'handlerClass' => EncryptedMessageHandler::class,
                'encrypted' => true,
                'nextExecutionTime' => new \DateTime('2000-01-01'),
                'exception' => \get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
            ],
        ], Context::createDefaultContext());

        $this->bus->expects(static::never())->method('dispatch');
        $this->encryptedBus->expects(static::never())->method('dispatch');
        $this->requeueDeadMessageService->requeue();

        $deadMessage = $this->deadMessageRepository->search(new Criteria([$encryptedId]), Context::createDefaultContext());

        static::assertCount(0, $deadMessage);
    }

    public function testRequeueDeadMessagesByClassname(): void
    {
        $msg = new EncryptedMessage('test');
        $e = new \Exception('exception');

        $testMessageId = Uuid::randomHex();
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $this->deadMessageRepository->create([
            [
                'id' => $testMessageId,
                'originalMessageClass' => TestMessage::class,
                'serializedOriginalMessage' => serialize($msg),
                'handlerClass' => EncryptedMessageHandler::class,
                'encrypted' => false,
                'nextExecutionTime' => new \DateTime('2000-01-01'),
                'exception' => \get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
            ],
            [
                'id' => $id1,
                'originalMessageClass' => EncryptedMessage::class,
                'serializedOriginalMessage' => serialize($msg),
                'handlerClass' => EncryptedMessageHandler::class,
                'encrypted' => false,
                'nextExecutionTime' => new \DateTime('2000-01-01'),
                'exception' => \get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
            ],
            [
                'id' => $id2,
                'originalMessageClass' => EncryptedMessage::class,
                'serializedOriginalMessage' => serialize($msg),
                'handlerClass' => EncryptedMessageHandler::class,
                'encrypted' => false,
                'nextExecutionTime' => (new \DateTime())->modify('+1 day'),
                'exception' => \get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
            ],
        ], Context::createDefaultContext());

        $this->bus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (RetryMessage $message) use ($testMessageId) {
                static::assertEquals($testMessageId, $message->getDeadMessageId());

                return true;
            }))
            ->willReturn(new Envelope(new RetryMessage($testMessageId)));

        $this->requeueDeadMessageService->requeue(TestMessage::class);
    }

    public function testDeadMessageWillBeDeletedAfterMaxRetries(): void
    {
        $msg = new EncryptedMessage('test');
        $e = new \Exception('exception');

        $encryptedId = Uuid::randomHex();

        $this->deadMessageRepository->create([
            [
                'id' => $encryptedId,
                'originalMessageClass' => LogCleanupTask::class,
                'serializedOriginalMessage' => serialize($msg),
                'handlerClass' => EncryptedMessageHandler::class,
                'encrypted' => true,
                'nextExecutionTime' => new \DateTime('2000-01-01'),
                'exception' => \get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
                'errorCount' => 4,
            ],
        ], Context::createDefaultContext());

        $this->logger->expects(static::once())->method('warning');
        $this->bus->expects(static::never())->method('dispatch');
        $this->encryptedBus->expects(static::never())->method('dispatch');
        $this->requeueDeadMessageService->requeue();

        $deadMessage = $this->deadMessageRepository->search(new Criteria([$encryptedId]), Context::createDefaultContext());

        static::assertCount(0, $deadMessage);
    }
}
