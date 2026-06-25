<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Transport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Transport\WebhookTransport;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @internal
 */
class WebhookTransportTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    public function testTransportServiceIsResolvable(): void
    {
        $transport = static::getContainer()->get('messenger.transport.webhook');

        static::assertInstanceOf(WebhookTransport::class, $transport);
    }

    public function testAsyncTransportIsResolvableWithoutCycling(): void
    {
        $transport = static::getContainer()->get('messenger.transport.async');

        static::assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testSendPersistsOutboxEntry(): void
    {
        $this->createWebhook('wh-1');

        $message = new WebhookEventMessage(
            $this->ids->get('evt-1'),
            ['body' => 'payload'],
            null,
            $this->ids->get('wh-1'),
            '6.7.0',
            'https://example.com/webhook',
            'test-secret',
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
        );

        $transport = static::getContainer()->get('messenger.transport.webhook');
        \assert($transport instanceof WebhookTransport);

        $transport->send(new Envelope($message));

        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertNotFalse($eventLog, 'Expected outbox entry to be created');
        static::assertSame('queued', $eventLog['delivery_status']);

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-1')]
        );

        static::assertNotFalse($delivery, 'Expected delivery row to be created');
        static::assertSame('queued', $delivery['delivery_status']);
    }

    private function createWebhook(string $key): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
