<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\SuspensionCause;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class WebhookHealthNotificationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EventDispatcherInterface $dispatcher;

    private IdsCollection $ids;

    private string $webhookName;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->dispatcher = static::getContainer()->get('event_dispatcher');
        $this->ids = new IdsCollection();
        $this->webhookName = 'health-notification-test-' . Uuid::randomHex();

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh'),
            'name' => $this->webhookName,
            'event_name' => 'test.event',
            'url' => 'https://example.com/hook',
            'active' => 1,
            'error_count' => 0,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM notification WHERE message LIKE :name',
            ['name' => '%' . $this->webhookName . '%']
        );
        $this->connection->delete('webhook', ['id' => $this->ids->getBytes('wh')]);
    }

    public function testSuspensionNotifiesExactlyOncePerEpisode(): void
    {
        $since = new \DateTimeImmutable('2026-06-01 12:00:00');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($since): void {
            $this->dispatcher->dispatch($this->suspendedEvent($since));
            $this->dispatcher->dispatch($this->suspendedEvent($since));
        });

        static::assertSame(1, $this->notificationCount(), 'one suspension episode = one notification');
        $row = $this->fetchNotification();
        static::assertSame('warning', $row['status']);
        static::assertSame('1', (string) $row['admin_only']);
        static::assertStringContainsString($this->webhookName, (string) $row['message']);
        static::assertStringNotContainsString('example.com', (string) $row['message'], 'messages carry name and state only — never the URL');
    }

    private function suspendedEvent(\DateTimeImmutable $since): WebhookSuspendedEvent
    {
        return new WebhookSuspendedEvent(
            $this->ids->get('wh'),
            null,
            EndpointState::Healthy,
            $since,
            SuspensionCause::AuthStreak,
            $this->webhookName,
            'checkout.order.placed',
            $since,
        );
    }

    private function notificationCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM notification WHERE message LIKE :name',
            ['name' => '%' . $this->webhookName . '%']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchNotification(): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT status, message, admin_only FROM notification WHERE message LIKE :name',
            ['name' => '%' . $this->webhookName . '%']
        );

        static::assertIsArray($row);

        return $row;
    }
}
