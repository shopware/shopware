<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\WebhookHealthTick;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Shopware\Core\Framework\Webhook\Transport\WebhookTransport;
use Shopware\Core\Framework\Webhook\Transport\WebhookTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookTransportFactory::class)]
class WebhookTransportFactoryTest extends TestCase
{
    public function testSupportsWebhookDsn(): void
    {
        $factory = $this->createFactory();

        static::assertTrue($factory->supports('shopware-webhook://default', []));
    }

    public function testDoesNotSupportOtherDsn(): void
    {
        $factory = $this->createFactory();

        static::assertFalse($factory->supports('shopware-webhook://custom', []));
        static::assertFalse($factory->supports('doctrine://default', []));
        static::assertFalse($factory->supports('amqp://localhost', []));
        static::assertFalse($factory->supports('', []));
    }

    public function testCreatesWebhookTransport(): void
    {
        $factory = $this->createFactory();

        $transport = $factory->createTransport(
            'shopware-webhook://default',
            [],
            static::createStub(SerializerInterface::class)
        );

        static::assertInstanceOf(WebhookTransport::class, $transport);
    }

    /**
     * Regression: deferred deps must not be resolved during construction.
     */
    public function testConstructorDoesNotResolveDeferredDependencies(): void
    {
        $calls = new class {
            public int $async = 0;

            public int $receiver = 0;
        };

        $factory = new WebhookTransportFactory(
            static::createStub(WebhookOutboxStore::class),
            function () use ($calls): TransportInterface {
                ++$calls->async;

                return $this->createStub(TransportInterface::class);
            },
            function () use ($calls): MySQLWebhookReceiver {
                ++$calls->receiver;

                return $this->createStub(MySQLWebhookReceiver::class);
            },
            static::createStub(WebhookHealthTick::class),
        );

        static::assertSame(0, $calls->async, 'Async transport must not be resolved at construction time.');
        static::assertSame(0, $calls->receiver, 'Receiver must not be resolved at construction time.');

        $factory->createTransport('shopware-webhook://default', [], static::createStub(SerializerInterface::class));

        static::assertSame(1, $calls->async, 'Async transport should be resolved exactly once when createTransport() is called.');
        static::assertSame(1, $calls->receiver, 'Receiver should be resolved exactly once when createTransport() is called.');
    }

    private function createFactory(): WebhookTransportFactory
    {
        $asyncTransport = static::createStub(TransportInterface::class);
        $receiver = static::createStub(MySQLWebhookReceiver::class);

        return new WebhookTransportFactory(
            static::createStub(WebhookOutboxStore::class),
            fn (): TransportInterface => $asyncTransport,
            fn (): MySQLWebhookReceiver => $receiver,
            static::createStub(WebhookHealthTick::class),
        );
    }
}
