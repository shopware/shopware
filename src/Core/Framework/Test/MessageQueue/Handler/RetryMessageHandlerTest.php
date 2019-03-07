<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Shopware\Core\Framework\MessageQueue\Handler\RetryMessageHandler;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\DummyHandler;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class RetryMessageHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $deadMessageRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RetryMessageHandler
     */
    private $retryMessageHandler;

    public function setUp(): void
    {
        $this->deadMessageRepository = $this->getContainer()->get('dead_message.repository');
        $this->context = Context::createDefaultContext();

        $this->retryMessageHandler = new RetryMessageHandler($this->getContainer(), $this->deadMessageRepository);
    }

    public function testGetHandledMessages(): void
    {
        $subscribedMessages = $this->retryMessageHandler::getHandledMessages();

        static::assertCount(1, $subscribedMessages);
        static::assertEquals(RetryMessage::class, $subscribedMessages[0]);
    }

    public function testWithMissingDeadMessage(): void
    {
        ($this->retryMessageHandler)(new RetryMessage(Uuid::uuid4()->getHex()));

        $messages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(0, $messages);
    }

    public function testWithSuccessfulRetry(): void
    {
        $message = new TestMessage();
        $deadMessageId = Uuid::uuid4()->getHex();

        $dummyHandler = new DummyHandler();
        $this->getContainer()->set(DummyHandler::class, $dummyHandler);

        $e = new \Exception('exception');
        $this->insertDeadMessage($deadMessageId, $message, $e);

        ($this->retryMessageHandler)(new RetryMessage($deadMessageId));

        $messages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(0, $messages);

        static::assertEquals($message, $dummyHandler->getLastMessage());
    }

    public function testWithFailingRetry(): void
    {
        $message = new TestMessage();
        $deadMessageId = Uuid::uuid4()->getHex();
        $e = new \Exception('will be thrown');

        $dummyHandler = (new DummyHandler())->willThrowException($e);
        $this->getContainer()->set(DummyHandler::class, $dummyHandler);

        $this->insertDeadMessage($deadMessageId, $message, $e);

        $catched = null;
        try {
            ($this->retryMessageHandler)(new RetryMessage($deadMessageId));
        } catch (\Exception $exception) {
            $catched = $exception;
        }

        static::assertInstanceOf(MessageFailedException::class, $catched);
        static::assertEquals($e, $catched->getPrevious());
        $messages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $messages);

        static::assertEquals($message, $dummyHandler->getLastMessage());
    }

    private function insertDeadMessage(string $deadMessageId, TestMessage $message, \Exception $e): void
    {
        $this->deadMessageRepository->create([
            [
                'id' => $deadMessageId,
                'originalMessageClass' => get_class($message),
                'serializedOriginalMessage' => serialize($message),
                'handlerClass' => DummyHandler::class,
                'encrypted' => false,
                'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime(1),
                'exception' => get_class($e),
                'exceptionMessage' => $e->getMessage(),
                'exceptionFile' => $e->getFile(),
                'exceptionLine' => $e->getLine(),
            ],
        ], $this->context);
    }
}
