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
 * The health API surface (#16565). All routes are gated behind WEBHOOKS_REWORK: with the
 * flag off, every route 404s.
 *
 * The two app-credential routes resolve the calling app from the integration on the token
 * and scope every query to that app. So one app can neither read nor reactivate another
 * app's webhooks. The two admin routes belong to the operator: the tick heartbeat for
 * self-hosted observability, and the dedicated deactivate action — the kill switch that
 * carries intent in any state, covering what `PATCH active = false` cannot express on a
 * webhook whose mirrored value is already false.
 *
 * Reads query the internal `webhook_health` table directly (no DAL — it has no entity
 * definition). Writes delegate to {@see WebhookHealthService}, which owns the guarded,
 * idempotent, concurrency-safe transitions.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class WebhookHealthController
{
    /**
     * One reactivation request may name at most this many webhooks. This is a self-service
     * batch, not a bulk job. The rate limiter bounds how often requests come in; this
     * bounds the work per request.
     */
    public const MAX_REACTIVATION_NAMES = 50;

    /**
     * @internal
     */
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

        $appId = $this->resolveAppId($context);

        // LEFT JOIN: a webhook with no health row counts as HEALTHY (the service's fail-open
        // contract), so COALESCE the missing columns to the healthy baseline here. The mirrored
        // active/error_count pair is returned next to endpoint_state (ADR §Admin API
        // backwards-compat). suspended_since/disabled_since bound the time window the vendor
        // reconciles against; disabled_origin says whose gesture a kill was.
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

        // The SELECT aliases already shape the response; only the two non-string columns need a cast.
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

        $source = $this->getAdminApiSource($context);
        $integrationId = $this->requireIntegrationId($source);

        // Throttle before doing any work (app lookup, payload parse): the limiter bounds the
        // request rate per integration no matter the payload, so a malformed or oversized body
        // is charged too.
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

        if (\count($names) > self::MAX_REACTIVATION_NAMES) {
            throw WebhookException::tooManyReactivationNames(\count($names), self::MAX_REACTIVATION_NAMES);
        }

        $reactivated = [];
        // Resolve names only within this app: a name that belongs to another app simply does
        // not resolve and is silently dropped. An app cannot reactivate another app's webhook.
        foreach ($this->resolveOwnedWebhooks($appId, $names) as $name => $webhook) {
            // An operator's explicit kill is refused: that recovery belongs to the merchant
            // (ADR §Schema and APIs). The response names the refusal so the vendor knows whom to ask.
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

        // The one tick carries every clocked duty, so its heartbeat covers the whole model. It is
        // written by whichever delivery worker completed a tick last; absent means never ticked —
        // typically no worker is consuming the webhook transport.
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

    private function requireIntegrationId(AdminApiSource $source): string
    {
        $integrationId = $source->getIntegrationId();
        if ($integrationId === null) {
            throw WebhookException::missingIntegration();
        }

        return $integrationId;
    }

    private function resolveAppId(Context $context): string
    {
        return $this->loadAppId($this->requireIntegrationId($this->getAdminApiSource($context)));
    }

    private function loadAppId(string $integrationId): string
    {
        // app <-> integration is 1:1 (each app install gets its own dedicated integration,
        // modelled as a DAL OneToOne), so fetchOne resolves a single app. Core auth's
        // ApiRequestContextResolver::fetchPermissionsIntegrationByApp trusts the same invariant.
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
            // This no-userId check — not the route ACL — is the real user-only security boundary.
            // An app manifest may declare any <permission>, including system.plugin_maintain, and
            // isAllowed() matches it verbatim, so an app credential can pass the ACL. But it has
            // no userId, so it is rejected here. 403, not 400: it authenticated, but is not a user.
            throw WebhookException::operatorRouteRequiresUser();
        }
    }

    /**
     * Stale when no tick ever completed or the last one is more than two intervals old
     * (one missed tick plus slack). A corrupt timestamp also reads as stale.
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
