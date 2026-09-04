<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\WebhookHealthTick;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Api\WebhookHealthControllerTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class WebhookHealthController
{
    /**
     * Bounds the work performed by each rate-limited request.
     */
    public const MAX_REACTIVATION_NAMES = 50;

    public function __construct(
        private readonly Connection $connection,
        private readonly RateLimiter $rateLimiter,
        private readonly WebhookHealthService $webhookHealthService,
        private readonly ClockInterface $clock,
        private readonly AbstractKeyValueStorage $keyValueStorage,
    ) {
    }

    #[Route(
        path: '/api/app-system/webhook/state',
        name: 'api.app_system.webhook.state',
        methods: [Request::METHOD_GET]
    )]
    public function state(Context $context): JsonResponse
    {
        $this->assertHealthApiEnabled();

        $appId = $this->loadAppId($this->getIntegrationId($context));

        // webhook_health has no DAL definition; missing rows use the fail-open HEALTHY baseline.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT w.name AS name,
                    COALESCE(wh.endpoint_state, :healthy) AS endpointState,
                    w.active AS active,
                    w.error_count AS errorCount,
                    wh.cooldown_until AS cooldownUntil,
                    wh.suspended_since AS suspendedSince,
                    wh.disabled_since AS disabledSince,
                    wh.disabled_origin AS disabledOrigin,
                    w.url AS url
             FROM webhook w
             LEFT JOIN webhook_health wh ON wh.webhook_id = w.id
             WHERE w.app_id = :appId
             ORDER BY w.name ASC',
            ['appId' => Uuid::fromHexToBytes($appId), 'healthy' => EndpointState::Healthy->value]
        );

        $webhooks = array_map(static function (array $row): array {
            $row['active'] = (bool) $row['active'];
            $row['errorCount'] = (int) $row['errorCount'];

            return $row;
        }, $rows);

        return new JsonResponse(['webhooks' => $webhooks]);
    }

    #[Route(
        path: '/api/app-system/webhook/reactivate',
        name: 'api.app_system.webhook.reactivate',
        methods: [Request::METHOD_POST]
    )]
    public function reactivate(Request $request, Context $context): JsonResponse
    {
        $this->assertHealthApiEnabled();

        $integrationId = $this->getIntegrationId($context);

        // Charge malformed and oversized requests to the integration too.
        try {
            $this->rateLimiter->ensureAccepted('webhook_reactivation', $integrationId);
        } catch (RateLimitExceededException $exception) {
            throw WebhookException::reactivationThrottled($exception->getWaitTime(), $exception);
        }

        $appId = $this->loadAppId($integrationId);

        try {
            /** @var list<mixed> $namesInput */
            $namesInput = $request->getPayload()->all('names');
        } catch (BadRequestException) {
            throw WebhookException::invalidReactivationNames();
        }
        $names = array_values(array_unique(array_filter($namesInput, \is_string(...))));

        $nameCount = \count($names);
        if ($nameCount > self::MAX_REACTIVATION_NAMES) {
            throw WebhookException::tooManyReactivationNames($nameCount, self::MAX_REACTIVATION_NAMES);
        }

        $reactivated = [];
        foreach ($this->resolveOwnedWebhooks($appId, $names) as $name => $webhook) {
            // App self-service must not override an operator decision.
            if ($webhook['disabledOrigin'] === DisabledOrigin::Operator->value) {
                $reactivated[] = [
                    'name' => $name,
                    'url' => $webhook['url'],
                    'reset' => false,
                    'refused' => 'operator_disabled',
                ];

                continue;
            }

            $reset = $this->webhookHealthService->reactivate($webhook['id'], WebhookActivationTrigger::AppReactivateApi);
            $reactivated[] = [
                'name' => $name,
                'url' => $webhook['url'],
                'reset' => $reset === 1,
            ];
        }

        return new JsonResponse(['reactivated' => $reactivated]);
    }

    #[Route(
        path: '/api/_action/webhook/health-status',
        name: 'api.action.webhook.health_status',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system.plugin_maintain']],
        methods: [Request::METHOD_GET]
    )]
    public function healthStatus(Context $context): JsonResponse
    {
        $this->assertHealthApiEnabled();
        $this->assertHasUserId($context);

        $lastTickAt = $this->keyValueStorage->get(WebhookHealthTick::HEARTBEAT_STORAGE_KEY);
        $lastTickAt = \is_string($lastTickAt) ? $lastTickAt : null;

        return new JsonResponse([
            'lastTickAt' => $lastTickAt,
            'stale' => $this->isStale($lastTickAt),
        ]);
    }

    #[Route(
        path: '/api/_action/webhook/{webhookId}/deactivate',
        name: 'api.action.webhook.deactivate',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system.plugin_maintain']],
        methods: [Request::METHOD_POST]
    )]
    public function deactivate(string $webhookId, Context $context): JsonResponse
    {
        $this->assertHealthApiEnabled();
        $this->assertHasUserId($context);

        if (!Uuid::isValid($webhookId)) {
            throw WebhookException::webhookNotFound($webhookId);
        }

        $exists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );
        if (!$exists) {
            throw WebhookException::webhookNotFound($webhookId);
        }

        return new JsonResponse(['disabled' => $this->webhookHealthService->disableByOperator($webhookId) === 1]);
    }

    private function assertHealthApiEnabled(): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            throw WebhookException::healthApiDisabled();
        }
    }

    private function getAdminApiSource(Context $context): AdminApiSource
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw WebhookException::missingIntegration();
        }

        return $source;
    }

    private function getIntegrationId(Context $context): string
    {
        $source = $this->getAdminApiSource($context);
        $integrationId = $source->getIntegrationId();
        if ($integrationId === null) {
            throw WebhookException::missingIntegration();
        }

        return $integrationId;
    }

    private function loadAppId(string $integrationId): string
    {
        // Every app installation has one dedicated integration.
        $appId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM app WHERE integration_id = :integrationId',
            ['integrationId' => Uuid::fromHexToBytes($integrationId)]
        );

        if (!\is_string($appId)) {
            throw WebhookException::appNotFoundForIntegration($integrationId);
        }

        return $appId;
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, array{id: string, url: string|null, disabledOrigin: string|null}> name => details
     */
    private function resolveOwnedWebhooks(string $appId, array $names): array
    {
        if ($names === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT w.name AS name, LOWER(HEX(w.id)) AS id, w.url AS url, wh.disabled_origin AS disabledOrigin
             FROM webhook w
             LEFT JOIN webhook_health wh ON wh.webhook_id = w.id
             WHERE w.app_id = :appId AND w.name IN (:names)',
            ['appId' => Uuid::fromHexToBytes($appId), 'names' => $names],
            ['names' => ArrayParameterType::STRING]
        );

        $owned = [];
        foreach ($rows as $row) {
            $owned[(string) $row['name']] = [
                'id' => (string) $row['id'],
                'url' => \is_string($row['url']) ? $row['url'] : null,
                'disabledOrigin' => \is_string($row['disabledOrigin']) ? $row['disabledOrigin'] : null,
            ];
        }

        return $owned;
    }

    private function assertHasUserId(Context $context): void
    {
        if ($this->getAdminApiSource($context)->getUserId() === null) {
            // App permissions can satisfy the route ACL; userId is the user-only boundary.
            throw WebhookException::operatorRouteRequiresUser();
        }
    }

    /**
     * Allows one missed interval; missing or invalid heartbeats are stale.
     */
    private function isStale(?string $lastTickAt): bool
    {
        if ($lastTickAt === null) {
            return true;
        }

        try {
            $lastTick = new \DateTimeImmutable($lastTickAt);
        } catch (\Exception) {
            return true;
        }

        return $lastTick->getTimestamp() + 2 * WebhookHealthTick::INTERVAL_SECONDS < $this->clock->now()->getTimestamp();
    }
}
