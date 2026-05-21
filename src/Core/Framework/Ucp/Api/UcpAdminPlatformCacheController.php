<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpPlatformProfileCacheCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpPlatformProfileCacheEntity;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Exposes the cached platform profiles for operator inspection and manual
 * invalidation when needed.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UcpAdminPlatformCacheController
{
    /**
     * @param EntityRepository<UcpPlatformProfileCacheCollection> $platformProfileCacheRepository
     */
    public function __construct(
        private readonly EntityRepository $platformProfileCacheRepository,
    ) {
    }

    #[Route(
        path: '/api/_admin/ucp/platform-profiles',
        name: 'api.ucp.admin.platform_cache.list',
        defaults: ['_acl' => ['ucp.viewer']],
        methods: ['GET']
    )]
    public function list(Context $context): JsonResponse
    {
        $this->guardFeatureFlag();
        $entries = $this->platformProfileCacheRepository->search(new Criteria(), $context);

        $items = [];
        foreach ($entries as $entry) {
            \assert($entry instanceof UcpPlatformProfileCacheEntity);
            $items[] = [
                'id' => $entry->getId(),
                'profileUri' => $entry->getProfileUri(),
                'fetchedAt' => $entry->getFetchedAt()->format(\DateTimeInterface::ATOM),
                'expiresAt' => $entry->getExpiresAt()->format(\DateTimeInterface::ATOM),
                'verificationStatus' => $entry->getVerificationStatus(),
                'failureCount' => $entry->getFailureCount(),
            ];
        }

        return new JsonResponse(['items' => $items]);
    }

    #[Route(
        path: '/api/_admin/ucp/platform-profiles/{id}',
        name: 'api.ucp.admin.platform_cache.delete',
        defaults: ['_acl' => ['ucp.editor']],
        methods: ['DELETE']
    )]
    public function delete(string $id, Context $context): JsonResponse
    {
        $this->guardFeatureFlag();
        $this->platformProfileCacheRepository->delete([['id' => $id]], $context);

        return new JsonResponse(null, 204);
    }

    private function guardFeatureFlag(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }
    }
}
