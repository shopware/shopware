<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
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

        static::assertSame(1, $this->notificationCount());
        $row = $this->fetchNotification();
        static::assertSame('warning', $row['status']);
        static::assertSame('1', (string) $row['admin_only']);
        static::assertStringContainsString($this->webhookName, (string) $row['message']);
        static::assertStringNotContainsString('example.com', (string) $row['message']);
    }

    public function testANewEpisodeAfterRecoveryNotifiesAgain(): void
    {
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatcher->dispatch($this->suspendedEvent(new \DateTimeImmutable('2026-06-01 12:00:00')));
            $this->dispatcher->dispatch($this->suspendedEvent(new \DateTimeImmutable('2026-06-03 09:30:00')));
        });

        static::assertSame(2, $this->notificationCount());
    }

    public function testDisabledAlwaysNotifiesAndNamesTheOrigin(): void
    {
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatcher->dispatch(new WebhookDisabledEvent(
                $this->ids->get('wh'),
                null,
                EndpointState::Suspended,
                DisabledOrigin::Escalation,
                $this->webhookName,
                'checkout.order.placed',
                new \DateTimeImmutable('2026-06-05 10:00:00'),
            ));
            $this->dispatcher->dispatch(new WebhookDisabledEvent(
                $this->ids->get('wh'),
                null,
                EndpointState::Healthy,
                DisabledOrigin::Operator,
                $this->webhookName,
                'checkout.order.placed',
                new \DateTimeImmutable('2026-06-05 10:00:00'),
            ));
        });

        static::assertSame(2, $this->notificationCount());
        $messages = $this->connection->fetchFirstColumn(
            'SELECT message FROM notification WHERE message LIKE :name ORDER BY id',
            ['name' => '%' . $this->webhookName . '%']
        );
        $all = implode(' | ', array_map(strval(...), $messages));
        static::assertStringContainsString('automatically', $all);
        static::assertStringContainsString('by an operator', $all);
    }

    public function testRecoveryFromSuspensionPostsAPositiveNotice(): void
    {
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatcher->dispatch(new WebhookActivatedEvent(
                $this->ids->get('wh'),
                null,
                EndpointState::Degraded,
                WebhookActivationTrigger::Trial,
                $this->webhookName,
                'checkout.order.placed',
                new \DateTimeImmutable('2026-06-05 10:00:00'),
                new \DateTimeImmutable('2026-06-01 12:00:00'),
            ));
        });

        static::assertSame(1, $this->notificationCount());
        static::assertSame('positive', $this->fetchNotification()['status']);
    }

    public function testActivationWithoutASuspensionEpisodeIsSilent(): void
    {
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatcher->dispatch(new WebhookActivatedEvent(
                $this->ids->get('wh'),
                null,
                EndpointState::Degraded,
                WebhookActivationTrigger::Idle,
                $this->webhookName,
                'checkout.order.placed',
                new \DateTimeImmutable('2026-06-05 10:00:00'),
                null,
            ));
        });

        static::assertSame(0, $this->notificationCount());
    }

    public function testFlagOffWritesNothing(): void
    {
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatcher->dispatch($this->suspendedEvent(new \DateTimeImmutable('2026-06-01 12:00:00')));
        });

        static::assertSame(0, $this->notificationCount());
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
