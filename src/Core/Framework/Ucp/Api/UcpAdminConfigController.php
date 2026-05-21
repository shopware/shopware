<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSalesChannelConfigCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Ucp\UcpVersion;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Admin-API endpoints for configuring UCP per Sales Channel.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UcpAdminConfigController
{
    /**
     * @param EntityRepository<UcpSalesChannelConfigCollection> $configRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $configRepository,
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    #[Route(
        path: '/api/_admin/ucp/sales-channels',
        name: 'api.ucp.admin.sales_channels.list',
        defaults: ['_acl' => ['ucp.viewer']],
        methods: ['GET']
    )]
    public function listSalesChannels(Context $context): JsonResponse
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }

        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context);
        $configs = $this->configRepository->search(new Criteria(), $context);

        $byChannel = [];
        foreach ($configs as $config) {
            \assert($config instanceof UcpSalesChannelConfigEntity);
            $byChannel[$config->getSalesChannelId()] = $config;
        }

        $rows = [];
        foreach ($salesChannels as $salesChannel) {
            $cfg = $byChannel[$salesChannel->getId()] ?? null;
            $rows[] = [
                'salesChannelId' => $salesChannel->getId(),
                'salesChannelName' => $salesChannel->getName(),
                'configured' => $cfg !== null,
                'active' => $cfg?->isActive() ?? false,
                'ucpVersion' => $cfg?->getUcpVersion(),
                'enabledCapabilities' => $cfg?->getEnabledCapabilities() ?? [],
                'enabledTransports' => $cfg?->getEnabledTransports() ?? [],
            ];
        }

        return new JsonResponse(['items' => $rows]);
    }

    #[Route(
        path: '/api/_admin/ucp/sales-channels/{id}/config',
        name: 'api.ucp.admin.config.read',
        defaults: ['_acl' => ['ucp.viewer']],
        methods: ['GET']
    )]
    public function readConfig(string $id, Context $context): JsonResponse
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }
        $entity = $this->loadConfig($id, $context);

        return new JsonResponse([
            'id' => $entity?->getId(),
            'salesChannelId' => $id,
            'active' => $entity?->isActive() ?? false,
            'ucpVersion' => $entity?->getUcpVersion() ?? UcpVersion::CURRENT,
            'profileUriStrategy' => $entity?->getProfileUriStrategy() ?? UcpSalesChannelConfigEntity::STRATEGY_DOMAIN,
            'customProfileUri' => $entity?->getCustomProfileUri(),
            'enabledCapabilities' => $entity?->getEnabledCapabilities() ?? $this->defaultCapabilities(),
            'enabledTransports' => $entity?->getEnabledTransports() ?? ['rest'],
            'continueUrlTemplate' => $entity?->getContinueUrlTemplate(),
            'platformAllowlist' => $entity?->getPlatformAllowlist(),
            'discoveryBudget' => $entity?->getDiscoveryBudget(),
            'webhookUrlOverride' => $entity?->getWebhookUrlOverride(),
            'signaturePolicy' => $entity?->getSignaturePolicy() ?? UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT,
            'idempotencyRequired' => $entity?->isIdempotencyRequired() ?? true,
        ]);
    }

    #[Route(
        path: '/api/_admin/ucp/sales-channels/{id}/config',
        name: 'api.ucp.admin.config.write',
        defaults: ['_acl' => ['ucp.editor']],
        methods: ['PUT', 'POST']
    )]
    public function writeConfig(string $id, Request $request, Context $context): JsonResponse
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }

        $payload = json_decode((string) $request->getContent(), true) ?? [];
        if (!\is_array($payload)) {
            $payload = [];
        }

        $existing = $this->loadConfig($id, $context);
        $entityId = $existing?->getId() ?? Uuid::randomHex();

        $writeData = array_filter([
            'id' => $entityId,
            'salesChannelId' => $id,
            'active' => (bool) ($payload['active'] ?? $existing?->isActive() ?? false),
            'ucpVersion' => $payload['ucpVersion'] ?? $existing?->getUcpVersion() ?? UcpVersion::CURRENT,
            'profileUriStrategy' => $payload['profileUriStrategy'] ?? $existing?->getProfileUriStrategy() ?? UcpSalesChannelConfigEntity::STRATEGY_DOMAIN,
            'customProfileUri' => $payload['customProfileUri'] ?? $existing?->getCustomProfileUri(),
            'enabledCapabilities' => $payload['enabledCapabilities'] ?? $existing?->getEnabledCapabilities() ?? $this->defaultCapabilities(),
            'enabledTransports' => $payload['enabledTransports'] ?? $existing?->getEnabledTransports() ?? ['rest'],
            'continueUrlTemplate' => $payload['continueUrlTemplate'] ?? $existing?->getContinueUrlTemplate(),
            'platformAllowlist' => $payload['platformAllowlist'] ?? $existing?->getPlatformAllowlist(),
            'discoveryBudget' => $payload['discoveryBudget'] ?? $existing?->getDiscoveryBudget(),
            'webhookUrlOverride' => $payload['webhookUrlOverride'] ?? $existing?->getWebhookUrlOverride(),
            'signaturePolicy' => $this->normaliseSignaturePolicy($payload['signaturePolicy'] ?? $existing?->getSignaturePolicy()),
            'idempotencyRequired' => isset($payload['idempotencyRequired'])
                ? (bool) $payload['idempotencyRequired']
                : ($existing?->isIdempotencyRequired() ?? true),
        ], static fn (mixed $v): bool => $v !== null);

        $this->configRepository->upsert([$writeData], $context);

        return new JsonResponse(['id' => $entityId, 'salesChannelId' => $id]);
    }

    private function loadConfig(string $salesChannelId, Context $context): ?UcpSalesChannelConfigEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->setLimit(1);
        $entity = $this->configRepository->search($criteria, $context)->first();

        return $entity instanceof UcpSalesChannelConfigEntity ? $entity : null;
    }

    /**
     * Whitelist `signaturePolicy` values so a malformed payload cannot push an
     * unknown enum value into the database. Falls back to the secure default
     * `strict` for any unrecognised input.
     */
    private function normaliseSignaturePolicy(mixed $value): string
    {
        $allowed = [
            UcpSalesChannelConfigEntity::SIGNATURE_POLICY_OFF,
            UcpSalesChannelConfigEntity::SIGNATURE_POLICY_LOG,
            UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT,
        ];

        if (\is_string($value) && \in_array($value, $allowed, true)) {
            return $value;
        }

        return UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT;
    }

    /**
     * @return list<string>
     */
    private function defaultCapabilities(): array
    {
        return [
            'dev.ucp.shopping.catalog.search',
            'dev.ucp.shopping.catalog.lookup',
            'dev.ucp.shopping.cart',
            'dev.ucp.shopping.checkout',
            'dev.ucp.shopping.order',
            'dev.ucp.shopping.discount',
            'dev.ucp.shopping.fulfillment',
            'dev.ucp.shopping.buyer_consent',
            'dev.ucp.common.identity_linking',
        ];
    }
}
