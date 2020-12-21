<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\DeadMessage;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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

    /**
     * @var EntityRepositoryInterface
     */
    private $deadMessageRepository;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var MessageBusInterface
     */
    private $encryptedBus;

    /**
     * @var RequeueDeadMessagesService
     */
    private $requeueDeadMessageService;

    public function setUp(): void
    {
        $this->deadMessageRepository = $this->getContainer()->get('dead_message.repository');
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->encryptedBus = $this->createMock(MessageBusInterface::class);

        $this->requeueDeadMessageService = new RequeueDeadMessagesService(
            $this->deadMessageRepository,
            $this->bus,
            $this->encryptedBus
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
}
