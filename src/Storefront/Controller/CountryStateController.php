<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoadedHook;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('system-settings')]
class CountryStateController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly CountryStateDataPageletLoader $countryStateDataPageletLoader)
    {
    }

    #[Route(path: 'country/country-state-data', name: 'frontend.country.country.data', defaults: ['XmlHttpRequest' => true, '_httpCache' => true], methods: ['POST'])]
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
