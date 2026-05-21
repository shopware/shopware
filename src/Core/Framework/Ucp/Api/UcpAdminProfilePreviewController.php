<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Ucp\Discovery\UcpConfigProvider;
use Shopware\Core\Framework\Ucp\Discovery\UcpProfileBuilder;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Exposes the rendered `/.well-known/ucp` profile preview in the admin UI so
 * operators can verify their configuration before exposing it publicly.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UcpAdminProfilePreviewController
{
    /**
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly UcpConfigProvider $configProvider,
        private readonly UcpProfileBuilder $profileBuilder,
        private readonly EntityRepository $salesChannelDomainRepository,
    ) {
    }

    #[Route(
        path: '/api/_admin/ucp/sales-channels/{id}/profile-preview',
        name: 'api.ucp.admin.profile_preview',
        defaults: ['_acl' => ['ucp.viewer']],
        methods: ['GET']
    )]
    public function preview(string $id, Context $context): JsonResponse
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }

        $config = $this->configProvider->forSalesChannel($id, $context);
        if ($config === null) {
            throw UcpException::salesChannelNotConfigured($id);
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $id))
            ->setLimit(1);
        $domain = $this->salesChannelDomainRepository->search($criteria, $context)->first();

        $profile = $this->profileBuilder->build(
            $config,
            $domain instanceof SalesChannelDomainEntity ? $domain : null,
            $context
        );

        return new JsonResponse($profile, 200, [], false);
    }
}
