<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('checkout')]
class SalutationRoute extends AbstractSalutationRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $salutationRepository,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(): string
    {
        return 'salutation-route';
    }

    public function getDecorated(): AbstractSalutationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/salutation', name: 'store-api.salutation', methods: ['GET', 'POST'], defaults: ['_entity' => 'salutation'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName());

        return new SalutationRouteResponse($this->salutationRepository->search($criteria, $context));
    }
}
