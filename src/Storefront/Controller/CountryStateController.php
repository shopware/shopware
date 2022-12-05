<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoadedHook;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 *
 * @package system-settings
 */
class CountryStateController extends StorefrontController
{
    private CountryStateDataPageletLoader $countryStateDataPageletLoader;

    /**
     * @deprecated tag:v6.5.0 - $countryRoute will be removed
     */
    private AbstractCountryRoute $countryRoute;

    /**
     * @internal
     */
    public function __construct(
        CountryStateDataPageletLoader $countryStateDataPageletLoader,
        AbstractCountryRoute $countryRoute
    ) {
        $this->countryStateDataPageletLoader = $countryStateDataPageletLoader;
        $this->countryRoute = $countryRoute;
    }

    /**
     * @Since("6.1.0.0")
     * This route should only be used by storefront to update address forms. It is not a replacement for store-api routes
     *
     * @HttpCache()
     * @Route("country/country-state-data", name="frontend.country.country.data", defaults={"csrf_protected"=false, "XmlHttpRequest"=true}, methods={ "POST" })
     */
    public function getCountryData(Request $request, SalesChannelContext $context): Response
    {
        $countryId = (string) $request->request->get('countryId');

        if (!$countryId) {
            throw new \InvalidArgumentException('Parameter countryId is empty');
        }

        $countryStateDataPagelet = $this->countryStateDataPageletLoader->load($countryId, $request, $context);

        $this->hook(new CountryStateDataPageletLoadedHook($countryStateDataPagelet, $context));

        /** @deprecated tag:v6.5.0 - stateRequired will be removed - remove complete if branch */
        if (!Feature::isActive('v6.5.0.0')) {
            $stateRequired = false;
            $countries = $this->countryRoute->load(
                new Request(),
                new Criteria([$countryId]),
                $context
            )->getCountries();

            if ($countries->first() !== null) {
                $stateRequired = $countries->first()->getForceStateInRegistration();
            }

            return new JsonResponse([
                'stateRequired' => $stateRequired, /** @deprecated tag:v6.5.0 - stateRequired will be removed */
                'states' => $countryStateDataPagelet->getStates(),
            ]);
        }

        return new JsonResponse([
            'states' => $countryStateDataPagelet->getStates(),
        ]);
    }
}
