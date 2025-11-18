<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\Event\CountryCriteriaEvent;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('fundamentals@discovery')]
class CountryRoute extends AbstractCountryRoute
{
    final public const ALL_TAG = 'country-route';

    /**
     * @internal
     *
     * @param SalesChannelRepository<CountryCollection> $countryRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $countryRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'country-route-' . $id;
    }

    #[Route(path: '/store-api/country', name: 'store-api.country', methods: ['GET', 'POST'], defaults: ['_entity' => 'country'])]
    public function load(Request $request, Criteria $criteria, SalesChannelContext $context): CountryRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getSalesChannelId()), self::ALL_TAG);

        $criteria->setTitle('country-route');
        $criteria->addFilter(new EqualsFilter('active', true));

        $this->dispatcher->dispatch(new CountryCriteriaEvent($request, $criteria, $context));
        $result = $this->countryRepository->search($criteria, $context);

        return new CountryRouteResponse($result);
    }

    protected function getDecorated(): AbstractCountryRoute
    {
        throw new DecorationPatternException(self::class);
    }
}
