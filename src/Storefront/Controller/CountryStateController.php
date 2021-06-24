<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CountryStateController
{
    private SalesChannelRepositoryInterface $countryRepository;

    public function __construct(SalesChannelRepositoryInterface $countryRepository)
    {
        $this->countryRepository = $countryRepository;
    }

    /**
     * @Since("6.1.0.0")
     * This route should only be used by storefront to update address forms. It is not a replacement for sales-channel-api routes
     *
     * @Route("country/country-state-data", name="frontend.country.country.data", defaults={"csrf_protected"=false, "XmlHttpRequest"=true}, methods={ "POST" })
     */
    public function getCountryData(Request $request, SalesChannelContext $context): Response
    {
        $countryId = (string) $request->request->get('countryId');
        $criteria = new Criteria([$countryId]);
        $criteria->addAssociation('states');

        /** @var CountryEntity|null $country */
        $country = $this->countryRepository->search($criteria, $context)->getEntities()->get($countryId);

        $country->getStates()->sortByPositionAndName();

        return new JsonResponse([
            'stateRequired' => $country->getForceStateInRegistration(),
            'states' => $country->getStates(),
        ]);
    }
}
