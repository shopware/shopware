<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
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
            $this->createMock(SerializerInterface::class)
        );

        static::assertInstanceOf(WebhookTransport::class, $transport);
    }

    private function createFactory(): WebhookTransportFactory
    {
        return new WebhookTransportFactory(
            $this->createMock(WebhookOutboxStore::class),
            $this->createMock(TransportInterface::class),
            $this->createMock(MySQLWebhookReceiver::class),
        );
    }
}
