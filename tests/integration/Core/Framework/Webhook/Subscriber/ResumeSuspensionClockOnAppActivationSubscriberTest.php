<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class ResumeSuspensionClockOnAppActivationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    private EventDispatcherInterface $eventDispatcher;

    private WebhookHealthService $healthService;

    private ClockInterface $clock;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
        $this->healthService = static::getContainer()->get(WebhookHealthService::class);
        $this->clock = static::getContainer()->get(ClockInterface::class);
    }

    public function testAppActivationShiftsTheSuspensionClockForwardOverTheDeactivatedInterval(): void
    {
        $now = $this->clock->now();
        $suspendedSince = $now->modify('-9 days');
        $deactivatedCursor = $now->modify('-4 days');

        $appId = $this->createApp('SwagResumeClockApp');
        $this->seedWebhook('wh', $appId);
        $this->seedSuspendedHealth('wh', $suspendedSince);

        $this->connection->update('app', ['active' => 0], ['id' => Uuid::fromHexToBytes($appId)]);
        $this->healthService->pauseSuspensionClockForApp($appId);

        $this->connection->update(
            'webhook_health',
            ['updated_at' => $deactivatedCursor->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['webhook_id' => $this->ids->getBytes('wh')],
        );

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($appId): void {
            $this->eventDispatcher->dispatch(new AppActivatedEvent($this->appEntity($appId), Context::createDefaultContext()));
        });

        $shifted = $this->fetchSuspendedSince('wh');
        static::assertNotNull($shifted);

        $expected = $now->modify('-5 days');
        static::assertEqualsWithDelta($expected->getTimestamp(), $shifted->getTimestamp(), 300);
    }

    private function appEntity(string $appId): AppEntity
    {
        return (new AppEntity())->assign(['id' => $appId]);
    }

    private function createApp(string $name): string
    {
        $appId = Uuid::randomHex();
        static::getContainer()->get('app.repository')->create([[
            'id' => $appId,
            'name' => $name,
            'path' => 'custom/apps/' . $name,
            'active' => true,
            'version' => '1.0.0',
            'label' => $name,
            'integration' => ['label' => $name, 'accessKey' => Uuid::randomHex(), 'secretAccessKey' => Uuid::randomHex()],
            'aclRole' => ['name' => $name],
        ]], Context::createDefaultContext());

        return $appId;
    }

    private function seedWebhook(string $key, string $appId): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://example.com/' . $key,
            'app_id' => Uuid::fromHexToBytes($appId),
            'active' => 0,
            'error_count' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function seedSuspendedHealth(string $key, \DateTimeInterface $suspendedSince): void
    {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => EndpointState::Suspended->value,
            'suspended_since' => $suspendedSince->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function fetchSuspendedSince(string $key): ?\DateTimeImmutable
    {
        $value = $this->connection->fetchOne(
            'SELECT suspended_since FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)],
        );

        return \is_string($value) ? new \DateTimeImmutable($value) : null;
    }
}
