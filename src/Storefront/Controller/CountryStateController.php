<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoadedHook;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package system-settings
 *
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @internal
 *
 * @package system-settings
 */
class CountryStateController extends StorefrontController
{
    private CountryStateDataPageletLoader $countryStateDataPageletLoader;

    /**
     * @internal
     */
    public function __construct(
        CountryStateDataPageletLoader $countryStateDataPageletLoader
    ) {
        $this->countryStateDataPageletLoader = $countryStateDataPageletLoader;
    }

    /**
     * @Since("6.1.0.0")
     * This route should only be used by storefront to update address forms. It is not a replacement for store-api routes
     *
     * @Route("country/country-state-data", name="frontend.country.country.data", defaults={"XmlHttpRequest"=true, "_httpCache"=true}, methods={"POST"})
     */
    public function getCountryData(Request $request, SalesChannelContext $context): Response
    {
        $countryId = (string) $request->request->get('countryId');

        if (!$countryId) {
            throw new \InvalidArgumentException('Parameter countryId is empty');
        }

        $countryStateDataPagelet = $this->countryStateDataPageletLoader->load($countryId, $request, $context);

        $this->hook(new CountryStateDataPageletLoadedHook($countryStateDataPagelet, $context));

        return new JsonResponse([
            'states' => $countryStateDataPagelet->getStates(),
        ]);
    }
}
