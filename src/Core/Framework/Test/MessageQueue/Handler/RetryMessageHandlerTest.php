<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler;
use Shopware\Core\Content\Sitemap\ScheduledTask\SitemapMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Shopware\Core\Framework\MessageQueue\Handler\RetryMessageHandler;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\DummyHandler;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

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

        $this->retryMessageHandler = $this->getContainer()->get(RetryMessageHandler::class);
    }

    public function testGetHandledMessages(): void
    {
        /** @var array $subscribedMessages */
        $subscribedMessages = $this->retryMessageHandler::getHandledMessages();

        static::assertCount(1, $subscribedMessages);
        static::assertEquals(RetryMessage::class, $subscribedMessages[0]);
    }

    public function testWithMissingDeadMessage(): void
    {
        ($this->retryMessageHandler)(new RetryMessage(Uuid::randomHex()));

        $messages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(0, $messages);
    }

    public function testWithSuccessfulRetry(): void
    {
        $message = new TestMessage();
        $deadMessageId = Uuid::randomHex();

        $dummyHandler = new DummyHandler();

        $e = new \Exception('exception');
        $this->insertDeadMessage($deadMessageId, $message, $e);

        $retryMessageHandler = new RetryMessageHandler(
            $this->deadMessageRepository,
            [$dummyHandler],
            $this->getContainer()->get('logger')
        );
        ($retryMessageHandler)(new RetryMessage($deadMessageId));

        $messages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(0, $messages);

        static::assertEquals($message, $dummyHandler->getLastMessage());
    }

    public function testWithFailingRetry(): void
    {
        $message = new TestMessage();
        $deadMessageId = Uuid::randomHex();
        $e = new \Exception('will be thrown');

        $dummyHandler = (new DummyHandler())->willThrowException($e);

        $this->insertDeadMessage($deadMessageId, $message, $e);

        $catched = null;

        $retryMessageHandler = new RetryMessageHandler(
            $this->deadMessageRepository,
            [$dummyHandler],
            $this->getContainer()->get('logger')
        );

        try {
            ($retryMessageHandler)(new RetryMessage($deadMessageId));
        } catch (MessageFailedException $exception) {
            $catched = $exception;
        }

        static::assertInstanceOf(MessageFailedException::class, $catched);
        static::assertEquals($e, $catched->getException());
        $messages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $messages);

        static::assertEquals($message, $dummyHandler->getLastMessage());
    }

    public function testWithRealMessages(): void
    {
        $message = new SitemapMessage(null, null, null, null, true);
        $deadMessageId = Uuid::randomHex();

        $e = new \Exception('exception');
        $this->insertDeadMessage($deadMessageId, $message, $e, SitemapGenerateTaskHandler::class);

        ($this->retryMessageHandler)(new RetryMessage($deadMessageId));

        $messages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(0, $messages);
    }

    public function testWithOwnMessages(): void
    {
        $message = new SitemapMessage(null, null, null, null, true);
        $deadMessageId = Uuid::randomHex();

        $e = new \Exception('exception');
        $this->insertDeadMessage($deadMessageId, $message, $e, SitemapGenerateTaskHandler::class);

        $retryMessage = new RetryMessage($deadMessageId);
        $retryMessageId = Uuid::randomHex();
        $this->insertDeadMessage($retryMessageId, $retryMessage, $e, RetryMessageHandler::class);

        ($this->retryMessageHandler)(new RetryMessage($retryMessageId));

        $messages = $this->deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(0, $messages);
    }

    private function insertDeadMessage(string $deadMessageId, $message, \Exception $e, ?string $handlerClass = null): void
    {
        if (!$handlerClass) {
            $handlerClass = DummyHandler::class;
        }

        $this->deadMessageRepository->create([
            [
                'id' => $deadMessageId,
                'originalMessageClass' => get_class($message),
                'serializedOriginalMessage' => serialize($message),
                'handlerClass' => $handlerClass,
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
