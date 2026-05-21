<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Shopware\Core\Framework\Webhook\Transport\WebhookTransport;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookTransport::class)]
class WebhookTransportTest extends TestCase
{
    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testSendPersistsOutboxAndForwardsToAsyncWhenFlagOff(): void
    {
        $message = $this->makeMessage();
        $envelope = new Envelope($message);

        $stateService = $this->createMock(WebhookOutboxStore::class);
        $stateService->expects($this->once())
            ->method('recordOutboxEntry')
            ->with(static::callback(function (OutboxInsert $entry) use ($message): bool {
                return $entry->webhookEventId === $message->getWebhookEventId()
                    && $entry->webhookId === $message->getWebhookId();
            }));

        $asyncTransport = $this->createMock(TransportInterface::class);
        $asyncTransport->expects($this->once())
            ->method('send')
            ->with($envelope)
            ->willReturn($envelope);

        $transport = new WebhookTransport($stateService, $asyncTransport, $this->createMock(MySQLWebhookReceiver::class));

        static::assertSame($envelope, $transport->send($envelope));
    }

    public function testSendSkipsAsyncForwardWhenFlagOn(): void
    {
        $message = $this->makeMessage();
        $envelope = new Envelope($message);

        $stateService = $this->createMock(WebhookOutboxStore::class);
        $stateService->expects($this->once())->method('recordOutboxEntry');

        $asyncTransport = $this->createMock(TransportInterface::class);
        $asyncTransport->expects($this->never())->method('send');

        $transport = new WebhookTransport($stateService, $asyncTransport, $this->createMock(MySQLWebhookReceiver::class));

        static::assertSame($envelope, $transport->send($envelope));
    }

    public function testSendRejectsNonWebhookEventMessage(): void
    {
        $transport = new WebhookTransport(
            $this->createMock(WebhookOutboxStore::class),
            $this->createMock(TransportInterface::class),
            $this->createMock(MySQLWebhookReceiver::class),
        );

        $this->expectException(WebhookException::class);
        $transport->send(new Envelope(new \stdClass()));
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testGetReturnsEmptyWhenFlagOff(): void
    {
        $receiver = $this->createMock(MySQLWebhookReceiver::class);
        $receiver->expects($this->never())->method('get');

        $transport = new WebhookTransport(
            $this->createMock(WebhookOutboxStore::class),
            $this->createMock(TransportInterface::class),
            $receiver,
        );

        static::assertSame([], iterator_to_array($transport->get()));
    }

    public function testGetDelegatesToReceiverWhenFlagOn(): void
    {
        $envelope = new Envelope($this->makeMessage());
        $receiver = $this->createMock(MySQLWebhookReceiver::class);
        $receiver->expects($this->once())->method('get')->willReturn([$envelope]);

        $transport = new WebhookTransport(
            $this->createMock(WebhookOutboxStore::class),
            $this->createMock(TransportInterface::class),
            $receiver,
        );

        static::assertSame([$envelope], iterator_to_array($transport->get()));
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testLifecycleHooksNoOpWhenFlagOff(): void
    {
        $envelope = new Envelope($this->makeMessage());
        $receiver = $this->createMock(MySQLWebhookReceiver::class);
        $receiver->expects($this->never())->method('ack');
        $receiver->expects($this->never())->method('reject');
        $receiver->expects($this->never())->method('keepalive');

        $transport = new WebhookTransport(
            $this->createMock(WebhookOutboxStore::class),
            $this->createMock(TransportInterface::class),
            $receiver,
        );

        $transport->ack($envelope);
        $transport->reject($envelope);
        $transport->keepalive($envelope);
    }

    public function testLifecycleHooksDelegateWhenFlagOn(): void
    {
        $envelope = new Envelope($this->makeMessage());
        $receiver = $this->createMock(MySQLWebhookReceiver::class);
        $receiver->expects($this->once())->method('ack')->with($envelope);
        $receiver->expects($this->once())->method('reject')->with($envelope);
        $receiver->expects($this->once())->method('keepalive')->with($envelope, 5);

        $transport = new WebhookTransport(
            $this->createMock(WebhookOutboxStore::class),
            $this->createMock(TransportInterface::class),
            $receiver,
        );

        $transport->ack($envelope);
        $transport->reject($envelope);
        $transport->keepalive($envelope, 5);
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testOutboxEntryHasCorrectPartitionKey(): void
    {
        $appId = '0189a5b5c0c07272b90f8e9e5b6a4d99';
        $message = new WebhookEventMessage(
            '0189a5b5c0c07272b90f8e9e5b6a4d01',
            ['body' => 'payload'],
            $appId,
            '0189a5b5c0c07272b90f8e9e5b6a4d03',
            '6.7.0',
            'https://example.com/webhook',
            'test-secret',
            'en-GB',
            'en-GB',
            [],
            $appId,
        );
        $envelope = new Envelope($message);

        $expectedPartitionKey = Hasher::hashBinary($message->getPartitionKey(), 'xxh128');

        $stateService = $this->createMock(WebhookOutboxStore::class);
        $stateService->expects($this->once())
            ->method('recordOutboxEntry')
            ->with(static::callback(function (OutboxInsert $entry) use ($expectedPartitionKey): bool {
                return $entry->partitionKey === $expectedPartitionKey;
            }));

        $asyncTransport = $this->createMock(TransportInterface::class);
        $asyncTransport->expects($this->once())->method('send')->willReturn($envelope);

        $transport = new WebhookTransport($stateService, $asyncTransport, $this->createMock(MySQLWebhookReceiver::class));

        $transport->send($envelope);
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testOutboxEntrySerializedMessageIsPhpSerialize(): void
    {
        $message = $this->makeMessage();
        $envelope = new Envelope($message);

        $expectedSerialized = serialize($message);

        $stateService = $this->createMock(WebhookOutboxStore::class);
        $stateService->expects($this->once())
            ->method('recordOutboxEntry')
            ->with(static::callback(function (OutboxInsert $entry) use ($expectedSerialized): bool {
                return $entry->serializedMessage === $expectedSerialized;
            }));

        $asyncTransport = $this->createMock(TransportInterface::class);
        $asyncTransport->expects($this->once())->method('send')->willReturn($envelope);

        $transport = new WebhookTransport($stateService, $asyncTransport, $this->createMock(MySQLWebhookReceiver::class));

        $transport->send($envelope);
    }

    private function makeMessage(): WebhookEventMessage
    {
        return new WebhookEventMessage(
            '0189a5b5c0c07272b90f8e9e5b6a4d01',
            ['body' => 'payload'],
            null,
            '0189a5b5c0c07272b90f8e9e5b6a4d03',
            '6.7.0',
            'https://example.com/webhook',
            'test-secret',
            'en-GB',
            'en-GB',
        );
    }
}
