<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('buyers-experience')]
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
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'country-route-' . $id;
    }

    #[Route(path: '/store-api/country', name: 'store-api.country', methods: ['GET', 'POST'], defaults: ['_entity' => 'country'])]
    public function load(Request $request, Criteria $criteria, SalesChannelContext $context): CountryRouteResponse
    {
        $this->dispatcher->dispatch(new AddCacheTagEvent(
            self::buildName($context->getSalesChannelId()),
            self::ALL_TAG
        ));

        $criteria->addFilter(new EqualsFilter('active', true));

        $result = $this->countryRepository->search($criteria, $context);

        return new CountryRouteResponse($result);
    }

    protected function getDecorated(): AbstractCountryRoute
    {
        throw new DecorationPatternException(self::class);
    }
}
