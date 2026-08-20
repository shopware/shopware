<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Event;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDegradedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\SuspensionCause;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class WebhookLifecycleEventsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    /**
     * @var array<string, list<string>>
     */
    private array $fixtureIds = [];

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    protected function tearDown(): void
    {
        foreach (['webhook', 'app', 'integration', 'acl_role'] as $table) {
            foreach ($this->fixtureIds[$table] ?? [] as $id) {
                $this->connection->delete($table, ['id' => $id]);
            }
        }
    }

    public function testLifecycleEventsAreRegisteredAsBusinessEvents(): void
    {
        $classes = static::getContainer()->get(BusinessEventRegistry::class)->getClasses();

        foreach ([WebhookActivatedEvent::class, WebhookDegradedEvent::class, WebhookSuspendedEvent::class, WebhookDisabledEvent::class] as $eventClass) {
            static::assertContains($eventClass, $classes);
        }
    }

    public function testASubscribedAppReceivesTheSuspendedEventAsAnOrdinaryWebhookDelivery(): void
    {
        $appId = $this->seedAppWithLifecycleSubscription();
        // Raw SQL does not invalidate the manager cache.
        static::getContainer()->get(WebhookManager::class)->reset();

        $before = $this->deliveryCount();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($appId): void {
            static::getContainer()->get('event_dispatcher')->dispatch($this->suspendedEvent(Uuid::randomHex(), $appId));
        });

        static::assertSame($before + 1, $this->deliveryCount());
    }

    private function suspendedEvent(string $webhookId, string $appId): WebhookSuspendedEvent
    {
        return new WebhookSuspendedEvent(
            $webhookId,
            $appId,
            EndpointState::Healthy,
            new \DateTimeImmutable('2026-06-01 12:00:00'),
            SuspensionCause::AuthStreak,
            'order-sync',
            'checkout.order.placed',
            new \DateTimeImmutable('2026-06-01 12:00:00'),
        );
    }

    private function seedAppWithLifecycleSubscription(): string
    {
        $unique = Uuid::randomHex();
        $aclRoleId = Uuid::randomBytes();
        $integrationId = Uuid::randomBytes();
        $appId = Uuid::randomBytes();
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('acl_role', [
            'id' => $aclRoleId,
            'name' => 'role-' . $unique,
            'privileges' => json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
        $this->fixtureIds['acl_role'][] = $aclRoleId;

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => 'key-' . $unique,
            'secret_access_key' => 'secret-' . $unique,
            'label' => 'integration-' . $unique,
            'created_at' => $now,
        ]);
        $this->fixtureIds['integration'][] = $integrationId;

        $this->connection->insert('app', [
            'id' => $appId,
            'name' => 'app-' . $unique,
            'path' => '/dev/null',
            'version' => '1.0.0',
            'active' => 1,
            'app_secret' => 'app-secret-' . $unique,
            'integration_id' => $integrationId,
            'acl_role_id' => $aclRoleId,
            'created_at' => $now,
        ]);
        $this->fixtureIds['app'][] = $appId;

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('subscriber'),
            'name' => 'lifecycle-subscriber-' . $unique,
            'event_name' => WebhookSuspendedEvent::NAME,
            'url' => 'https://example.com/health-events',
            'app_id' => $appId,
            'active' => 1,
            'error_count' => 0,
            'created_at' => $now,
        ]);
        $this->fixtureIds['webhook'][] = $this->ids->getBytes('subscriber');

        return Uuid::fromBytesToHex($appId);
    }

    private function deliveryCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes('subscriber')]
        );
    }
}
