<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Transport\WebhookTransport;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookTransport::class)]
class WebhookTransportTest extends TestCase
{
    public function testSendPersistsOutboxAndForwardsToAsync(): void
    {
        $message = new WebhookEventMessage(
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
        $envelope = new Envelope($message);

        $repository = $this->createMock(OutboxEventRepository::class);
        $repository->expects($this->once())
            ->method('ensureOutboxEntry')
            ->with(static::callback(function (OutboxInsert $entry) use ($message): bool {
                return $entry->webhookEventId === $message->getWebhookEventId()
                    && $entry->webhookId === $message->getWebhookId();
            }));

        $asyncTransport = $this->createMock(TransportInterface::class);
        $asyncTransport->expects($this->once())
            ->method('send')
            ->with($envelope)
            ->willReturn($envelope);

        $transport = new WebhookTransport($repository, $asyncTransport);
        $result = $transport->send($envelope);

        static::assertSame($envelope, $result);
    }

    public function testSendRejectsNonWebhookEventMessage(): void
    {
        $transport = new WebhookTransport(
            $this->createMock(OutboxEventRepository::class),
            $this->createMock(TransportInterface::class),
        );

        $this->expectException(WebhookException::class);
        $transport->send(new Envelope(new \stdClass()));
    }

    public function testGetReturnsEmpty(): void
    {
        $transport = new WebhookTransport(
            $this->createMock(OutboxEventRepository::class),
            $this->createMock(TransportInterface::class),
        );

        static::assertSame([], iterator_to_array($transport->get()));
    }

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

        $repository = $this->createMock(OutboxEventRepository::class);
        $repository->expects($this->once())
            ->method('ensureOutboxEntry')
            ->with(static::callback(function (OutboxInsert $entry) use ($expectedPartitionKey): bool {
                return $entry->partitionKey === $expectedPartitionKey;
            }));

        $asyncTransport = $this->createMock(TransportInterface::class);
        $asyncTransport->expects($this->once())
            ->method('send')
            ->willReturn($envelope);

        $transport = new WebhookTransport($repository, $asyncTransport);
        $transport->send($envelope);
    }

    public function testOutboxEntrySerializedMessageIsPhpSerialize(): void
    {
        $message = new WebhookEventMessage(
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
        $envelope = new Envelope($message);

        $expectedSerialized = serialize($message);

        $repository = $this->createMock(OutboxEventRepository::class);
        $repository->expects($this->once())
            ->method('ensureOutboxEntry')
            ->with(static::callback(function (OutboxInsert $entry) use ($expectedSerialized): bool {
                return $entry->serializedMessage === $expectedSerialized;
            }));

        $asyncTransport = $this->createMock(TransportInterface::class);
        $asyncTransport->expects($this->once())
            ->method('send')
            ->willReturn($envelope);

        $transport = new WebhookTransport($repository, $asyncTransport);
        $transport->send($envelope);
    }

    public function testAsyncTransportReceivesOriginalEnvelope(): void
    {
        $message = new WebhookEventMessage(
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
        $envelope = new Envelope($message);

        $repository = $this->createMock(OutboxEventRepository::class);

        $asyncTransport = $this->createMock(TransportInterface::class);
        $asyncTransport->expects($this->once())
            ->method('send')
            ->with(static::identicalTo($envelope))
            ->willReturn($envelope);

        $transport = new WebhookTransport($repository, $asyncTransport);
        $result = $transport->send($envelope);

        static::assertSame($envelope, $result);
    }
}
