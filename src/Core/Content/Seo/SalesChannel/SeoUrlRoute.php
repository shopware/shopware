<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('inventory')]
class SeoUrlRoute extends AbstractSeoUrlRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<SeoUrlCollection> $salesChannelRepository
     */
    public function __construct(private readonly SalesChannelRepository $salesChannelRepository)
    {
    }

    public function getDecorated(): AbstractSeoUrlRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/seo-url', name: 'store-api.seo.url', methods: ['GET', 'POST'], defaults: ['_entity' => 'seo_url'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SeoUrlRouteResponse
    {
        return new SeoUrlRouteResponse($this->salesChannelRepository->search($criteria, $context));
    }
}
