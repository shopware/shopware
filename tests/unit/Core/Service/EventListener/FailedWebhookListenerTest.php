<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\EventListener;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Event\WebhookFailedEvent;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Service\EventListener\FailedWebhookListener;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * @internal
 */
#[CoversClass(FailedWebhookListener::class)]
class FailedWebhookListenerTest extends TestCase
{
    private Connection&MockObject $connectionMock;

    private FailedWebhookListener $listener;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->listener = new FailedWebhookListener($this->connectionMock);
    }

    public function testNonRequiredEventIsIgnored(): void
    {
        $message = $this->createMock(WebhookEventMessage::class);

        $message->method('getPayload')->willReturn(['data' => ['event' => 'some_other_event']]);
        $message->method('getAppId')->willReturn('1234');

        $event = new WebhookFailedEvent($message, new \Exception('test'), 1);

        $this->connectionMock->expects(static::never())->method('fetchOne');

        $this->listener->__invoke($event);
    }

    public function testMissingAppIdIsIgnored(): void
    {
        $message = $this->createMock(WebhookEventMessage::class);

        $message->method('getPayload')->willReturn(['data' => ['event' => 'shopware.updated']]);
        $message->method('getAppId')->willReturn(null);

        $event = new WebhookFailedEvent($message, new \Exception('test'), 1);

        $this->connectionMock->expects(static::never())->method('fetchOne');

        $this->listener->__invoke($event);
    }

    public function testNonSelfManagedAppIsIgnored(): void
    {
        $message = $this->createMock(WebhookEventMessage::class);

        $message->method('getPayload')->willReturn(['data' => ['event' => 'shopware.updated']]);
        $message->method('getAppId')->willReturn('1234');

        $event = new WebhookFailedEvent($message, new \Exception('test'), 1);

        $this->connectionMock->expects(static::once())
            ->method('fetchOne')
            ->with('SELECT self_managed FROM app WHERE id = UNHEX(:id)', ['id' => '1234'])
            ->willReturn(0);

        $this->listener->__invoke($event);
    }

    public function testExceptionIsThrownContainingDelay(): void
    {
        $message = $this->createMock(WebhookEventMessage::class);

        $message->method('getPayload')->willReturn(['data' => ['event' => 'shopware.updated']]);
        $message->method('getAppId')->willReturn('1234');

        $event = new WebhookFailedEvent($message, new \Exception('test'), 1);

        $this->connectionMock->expects(static::once())
            ->method('fetchOne')
            ->with('SELECT self_managed FROM app WHERE id = UNHEX(:id)', ['id' => '1234'])
            ->willReturn(1);

        try {
            $this->listener->__invoke($event);
        } catch (RecoverableMessageHandlingException $e) {
            static::assertSame(5000, $e->getRetryDelay());
        }
    }

    public function testExceptionIsThrownContainingLongDelayAfterMultipleAttempts(): void
    {
        $message = $this->createMock(WebhookEventMessage::class);

        $message->method('getPayload')->willReturn(['data' => ['event' => 'shopware.updated']]);
        $message->method('getAppId')->willReturn('1234');

        $event = new WebhookFailedEvent($message, new \Exception('test'), 6);

        $this->connectionMock->expects(static::once())
            ->method('fetchOne')
            ->with('SELECT self_managed FROM app WHERE id = UNHEX(:id)', ['id' => '1234'])
            ->willReturn(1);

        try {
            $this->listener->__invoke($event);
        } catch (RecoverableMessageHandlingException $e) {
            static::assertSame(86400000 * 2 /* 2 days */, $e->getRetryDelay());
        }
    }

    public function testAppIsQueriedOnce(): void
    {
        $message = $this->createMock(WebhookEventMessage::class);

        $message->method('getPayload')->willReturn(['data' => ['event' => 'shopware.updated']]);
        $message->method('getAppId')->willReturn('1234');

        $event = new WebhookFailedEvent($message, new \Exception('test'), 1);

        $this->connectionMock->expects(static::once())
            ->method('fetchOne')
            ->with('SELECT self_managed FROM app WHERE id = UNHEX(:id)', ['id' => '1234'])
            ->willReturn(0);

        $this->listener->__invoke($event);
        $this->listener->__invoke($event);
    }

    public function testAppIsQueriedAgainAfterReset(): void
    {
        $message = $this->createMock(WebhookEventMessage::class);

        $message->method('getPayload')->willReturn(['data' => ['event' => 'shopware.updated']]);
        $message->method('getAppId')->willReturn('1234');

        $event = new WebhookFailedEvent($message, new \Exception('test'), 1);

        $this->connectionMock->expects(static::exactly(2))
            ->method('fetchOne')
            ->with('SELECT self_managed FROM app WHERE id = UNHEX(:id)', ['id' => '1234'])
            ->willReturn(0);

        $this->listener->__invoke($event);
        $this->listener->reset();
        $this->listener->__invoke($event);
    }
}
