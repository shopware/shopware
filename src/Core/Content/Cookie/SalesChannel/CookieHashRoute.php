<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('framework')]
class CookieHashRoute extends AbstractCookieHashRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CookieProviderInterface $cookieProvider,
        private readonly CookieService $cookieService,
    ) {
    }

    public function getDecorated(): AbstractCookieHashRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cookie-hash', name: 'store-api.cookie.hash', methods: ['GET'])]
    public function getCookieHash(Request $request, SalesChannelContext $salesChannelContext): CookieHashRouteResponse
    {
        $translate = $request->query->getBoolean('translate', true); // Default to true for Store API consumers

        $cookieGroups = $this->cookieProvider->getCookieGroups();
        if (empty($cookieGroups)) {
            $collection = new CookieGroupCollection();

            return new CookieHashRouteResponse($this->cookieService->calculateCookieHash($collection));
        }

        $collection = $this->cookieService->getCookieGroupCollection(
            $cookieGroups,
            $salesChannelContext,
            $translate
        );

        return new CookieHashRouteResponse($this->cookieService->calculateCookieHash($collection));
    }
}
