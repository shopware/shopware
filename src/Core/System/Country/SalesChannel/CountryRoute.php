<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 *
 * @package system-settings
 */
class CountryRoute extends AbstractCountryRoute
{
    /**
     * @var SalesChannelRepository
     */
    private $countryRepository;

    /**
     * @internal
     */
    public function __construct(
        SalesChannelRepository $countryRepository
    ) {
        $this->countryRepository = $countryRepository;
    }

    /**
     * @Since("6.3.0.0")
     * @Entity("country")
     * @Route("/store-api/country", name="store-api.country", methods={"GET", "POST"})
     */
    public function load(Request $request, Criteria $criteria, SalesChannelContext $context): CountryRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('active', true));

        $result = $this->countryRepository->search($criteria, $context);

        return new CountryRouteResponse($result);
    }

    protected function getDecorated(): AbstractCountryRoute
    {
        throw new DecorationPatternException(self::class);
    }
}
