<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class WebhookHealthControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    private const URL = 'https://endpoint.example.com/hook';

    private Connection $connection;

    private IdsCollection $ids;

    public static function setUpBeforeClass(): void
    {
        // The test kernel otherwise replaces the limiter with NoLimiter.
        DisableRateLimiterCompilerPass::disableNoLimit();
        KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
    }

    public static function tearDownAfterClass(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
        KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
    }

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    public function testStateListsOnlyTheCallingAppsWebhooksWithHealthAndMirroredColumns(): void
    {
        $appId = $this->createAppForIntegration($this->ids->create('integration'));
        $otherAppId = $this->createApp();

        $cooldownUntil = $this->dateTime('+1 hour');
        $suspendedSince = $this->dateTime('-2 days');
        $disabledSince = $this->dateTime('-1 day');

        $this->seedWebhook('a-suspended', appId: $appId, active: false, errorCount: 5);
        $this->seedHealth('a-suspended', EndpointState::Suspended, cooldownUntil: $cooldownUntil, suspendedSince: $suspendedSince);
        $this->seedWebhook('b-disabled', appId: $appId, active: false, errorCount: 3);
        $this->seedHealth('b-disabled', EndpointState::Disabled, disabledSince: $disabledSince, disabledOrigin: DisabledOrigin::Operator);
        $this->seedWebhook('c-fresh', appId: $appId);
        $this->seedWebhook('d-theirs', appId: $otherAppId);
        $this->seedHealth('d-theirs', EndpointState::Degraded);

        $content = Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): array {
            $browser = $this->appBrowser('integration');
            $browser->request('GET', '/api/app-system/webhook/state');
            static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

            return $this->decode($browser);
        });

        static::assertSame([
            'webhooks' => [
                [
                    'name' => 'a-suspended',
                    'endpointState' => EndpointState::Suspended->value,
                    'active' => false,
                    'errorCount' => 5,
                    'cooldownUntil' => $cooldownUntil,
                    'suspendedSince' => $suspendedSince,
                    'disabledSince' => null,
                    'disabledOrigin' => null,
                    'url' => self::URL,
                ],
                [
                    'name' => 'b-disabled',
                    'endpointState' => EndpointState::Disabled->value,
                    'active' => false,
                    'errorCount' => 3,
                    'cooldownUntil' => null,
                    'suspendedSince' => null,
                    'disabledSince' => $disabledSince,
                    'disabledOrigin' => DisabledOrigin::Operator->value,
                    'url' => self::URL,
                ],
                [
                    'name' => 'c-fresh',
                    'endpointState' => EndpointState::Healthy->value,
                    'active' => true,
                    'errorCount' => 0,
                    'cooldownUntil' => null,
                    'suspendedSince' => null,
                    'disabledSince' => null,
                    'disabledOrigin' => null,
                    'url' => self::URL,
                ],
            ],
        ], $content);
    }

    public function testReactivateResetsSuspendedWebhookToHealthyAndResumesHeldRows(): void
    {
        $appId = $this->createAppForIntegration($this->ids->create('integration'));
        $this->seedWebhook('wh', appId: $appId, active: false, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Suspended, cooldownUntil: $this->dateTime('+1 hour'), suspendedSince: $this->dateTime('-2 days'));
        $this->seedHeldDelivery('evt', 'wh');

        $content = Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): array {
            $browser = $this->appBrowser('integration');
            $browser->request('POST', '/api/app-system/webhook/reactivate', [], [], [], $this->namesBody(['wh']));
            static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

            return $this->decode($browser);
        });

        static::assertSame(['reactivated' => [['name' => 'wh', 'url' => self::URL, 'reset' => true]]], $content);

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Healthy->value, $health['endpoint_state']);
        static::assertNull($health['cooldown_until']);
        static::assertNull($health['suspended_since']);

        static::assertSame(['active' => 1, 'error_count' => 0], $this->fetchMirroredColumns('wh'), 'reset re-mirrors the legacy columns');

        $statuses = $this->connection->fetchAssociative(
            'SELECT d.delivery_status AS delivery, el.delivery_status AS log
             FROM webhook_delivery d
             JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
             WHERE d.webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt')]
        );
        static::assertIsArray($statuses);
        static::assertSame(
            ['delivery' => WebhookEventLogDefinition::STATUS_PENDING_RETRY, 'log' => WebhookEventLogDefinition::STATUS_PENDING_RETRY],
            $statuses,
            'the held row is resumed on both mirrored tables'
        );
    }

    public function testReactivateRefusesOperatorDisabledWebhook(): void
    {
        $appId = $this->createAppForIntegration($this->ids->create('integration'));
        $this->seedWebhook('wh', appId: $appId, active: false);
        $this->seedHealth('wh', EndpointState::Disabled, disabledSince: $this->dateTime('-1 day'), disabledOrigin: DisabledOrigin::Operator);

        $content = Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): array {
            $browser = $this->appBrowser('integration');
            $browser->request('POST', '/api/app-system/webhook/reactivate', [], [], [], $this->namesBody(['wh']));
            static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

            return $this->decode($browser);
        });

        static::assertSame(
            ['reactivated' => [['name' => 'wh', 'url' => self::URL, 'reset' => false, 'refused' => 'operator_disabled']]],
            $content,
            'the response names the refusal so the vendor knows whom to ask'
        );

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Disabled->value, $health['endpoint_state'], 'an operator kill is not undone by app self-service');
        static::assertSame(DisabledOrigin::Operator->value, $health['disabled_origin']);
    }

    public function testReactivateIsRateLimitedPerIntegration(): void
    {
        $this->createAppForIntegration($this->ids->create('integration'));
        $this->createAppForIntegration($this->ids->create('other-integration'));

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $browser = $this->appBrowser('integration');
            $body = $this->namesBody([]);

            // The configured limit is 10 requests per minute.
            for ($i = 1; $i <= 10; ++$i) {
                $browser->request('POST', '/api/app-system/webhook/reactivate', [], [], [], $body);
                static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), 'call ' . $i . ' is within budget');
            }

            $browser->request('POST', '/api/app-system/webhook/reactivate', [], [], [], $body);
            $this->assertApiError($browser, Response::HTTP_TOO_MANY_REQUESTS, WebhookException::REACTIVATION_THROTTLED);

            $otherBrowser = $this->appBrowser('other-integration');
            $otherBrowser->request('POST', '/api/app-system/webhook/reactivate', [], [], [], $body);
            static::assertSame(Response::HTTP_OK, $otherBrowser->getResponse()->getStatusCode());
        });
    }

    public function testDeactivateDisablesSuspendedWebhookWithOperatorOrigin(): void
    {
        $appId = $this->createApp();
        $this->seedWebhook('wh', appId: $appId, active: false, errorCount: 5);
        $this->seedHealth('wh', EndpointState::Suspended, cooldownUntil: $this->dateTime('+1 hour'), suspendedSince: $this->dateTime('-2 days'));

        $content = Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): array {
            $admin = $this->getBrowser();
            $admin->request('POST', \sprintf('/api/_action/webhook/%s/deactivate', $this->ids->get('wh')));
            static::assertSame(Response::HTTP_OK, $admin->getResponse()->getStatusCode());

            return $this->decode($admin);
        });

        static::assertSame(['disabled' => true], $content);

        $health = $this->fetchHealthRow('wh');
        static::assertSame(EndpointState::Disabled->value, $health['endpoint_state']);
        static::assertSame(DisabledOrigin::Operator->value, $health['disabled_origin']);
        static::assertNull($health['cooldown_until'], 'the suspension cooldown is cleared by the kill');
    }

    public function testWholeSurfaceIsHiddenWhenFlagOff(): void
    {
        $appId = $this->createAppForIntegration($this->ids->create('integration'));
        $this->seedWebhook('wh', appId: $appId);

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            $app = $this->appBrowser('integration');
            $admin = $this->getBrowser();

            $app->request('GET', '/api/app-system/webhook/state');
            $this->assertApiError($app, Response::HTTP_NOT_FOUND, WebhookException::HEALTH_API_DISABLED);

            $app->request('POST', '/api/app-system/webhook/reactivate', [], [], [], $this->namesBody(['wh']));
            $this->assertApiError($app, Response::HTTP_NOT_FOUND, WebhookException::HEALTH_API_DISABLED);

            $admin->request('GET', '/api/_action/webhook/health-status');
            $this->assertApiError($admin, Response::HTTP_NOT_FOUND, WebhookException::HEALTH_API_DISABLED);

            $admin->request('POST', \sprintf('/api/_action/webhook/%s/deactivate', $this->ids->get('wh')));
            $this->assertApiError($admin, Response::HTTP_NOT_FOUND, WebhookException::HEALTH_API_DISABLED);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(TestBrowser $browser): array
    {
        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }

    private function assertApiError(TestBrowser $browser, int $status, string $errorCode): void
    {
        static::assertSame($status, $browser->getResponse()->getStatusCode());

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);
        static::assertStringContainsString($errorCode, $content);
    }

    /**
     * @param list<string> $names
     */
    private function namesBody(array $names): string
    {
        return json_encode(['names' => $names], \JSON_THROW_ON_ERROR);
    }

    private function dateTime(string $modifier = 'now'): string
    {
        return (new \DateTimeImmutable($modifier))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function appBrowser(string $integrationKey): TestBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->setServerParameter('HTTP_ACCEPT', 'application/json');
        $this->authorizeBrowserWithIntegration($browser, $this->ids->get($integrationKey));

        return $browser;
    }

    private function createAppForIntegration(string $integrationId): string
    {
        $appId = Uuid::randomBytes();
        $aclRoleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $aclRoleId,
            'name' => 'wh-health-' . Uuid::randomHex(),
            'privileges' => json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $this->dateTime(),
        ]);

        $this->ensureIntegration($integrationId);

        $this->connection->insert('app', [
            'id' => $appId,
            'name' => 'SwagWhHealth' . Uuid::randomHex(),
            'path' => '/dev/null',
            'version' => '1.0.0',
            'active' => 1,
            'app_secret' => 'app-secret',
            'integration_id' => Uuid::fromHexToBytes($integrationId),
            'acl_role_id' => $aclRoleId,
            'created_at' => $this->dateTime(),
        ]);

        return Uuid::fromBytesToHex($appId);
    }

    private function ensureIntegration(string $integrationId): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT 1 FROM integration WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($integrationId)]
        );
        if ($existing !== false) {
            return;
        }

        $this->connection->insert('integration', [
            'id' => Uuid::fromHexToBytes($integrationId),
            'access_key' => 'key-' . Uuid::randomHex(),
            'secret_access_key' => TestDefaults::HASHED_PASSWORD,
            'label' => 'wh-health-integration',
            'created_at' => $this->dateTime(),
        ]);
    }

    private function createApp(): string
    {
        return $this->createAppForIntegration(Uuid::randomHex());
    }

    private function seedWebhook(string $key, string $appId, bool $active = true, int $errorCount = 0): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'app_id' => Uuid::fromHexToBytes($appId),
            'active' => (int) $active,
            'error_count' => $errorCount,
            'created_at' => $this->dateTime(),
        ]);
    }

    private function seedHealth(
        string $key,
        EndpointState $state,
        ?string $cooldownUntil = null,
        ?string $suspendedSince = null,
        ?string $disabledSince = null,
        ?DisabledOrigin $disabledOrigin = null,
    ): void {
        $this->connection->insert('webhook_health', [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'consecutive_transient_failures' => 0,
            'consecutive_non_transient_failures' => 0,
            'degraded_cycle_count' => 0,
            'cooldown_until' => $cooldownUntil,
            'suspended_since' => $suspendedSince,
            'disabled_since' => $disabledSince,
            'disabled_origin' => $disabledOrigin?->value,
            'created_at' => $this->dateTime(),
        ]);
    }

    private function seedHeldDelivery(string $eventKey, string $webhookKey): void
    {
        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => WebhookEventLogDefinition::STATUS_PAUSED,
            'webhook_name' => $webhookKey,
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => self::URL,
            'created_at' => $this->dateTime(),
        ]);

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => $this->ids->getBytes($webhookKey),
            'partition_key' => Uuid::randomBytes(),
            'delivery_status' => WebhookEventLogDefinition::STATUS_PAUSED,
            'created_at' => $this->dateTime(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchHealthRow(string $key): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT endpoint_state, cooldown_until, suspended_since, disabled_since, disabled_origin
             FROM webhook_health WHERE webhook_id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
        static::assertIsArray($row);

        return $row;
    }

    /**
     * @return array{active: int, error_count: int}
     */
    private function fetchMirroredColumns(string $key): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => $this->ids->getBytes($key)]
        );
        static::assertIsArray($row);

        return ['active' => (int) $row['active'], 'error_count' => (int) $row['error_count']];
    }
}
