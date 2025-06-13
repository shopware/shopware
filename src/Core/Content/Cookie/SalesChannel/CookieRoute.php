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
class CookieRoute extends AbstractCookieRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CookieProviderInterface $cookieProvider,
        private readonly CookieService $cookieService,
    ) {
    }

    public function getDecorated(): AbstractCookieRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cookie-groups', name: 'store-api.cookie.groups', methods: ['GET'])]
    public function getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieRouteResponse
    {
        $translate = $request->query->getBoolean('translate', true); // Default to true for Store API consumers

        $cookieGroups = $this->cookieProvider->getCookieGroups();
        if (empty($cookieGroups)) {
            return new CookieRouteResponse(new CookieGroupCollection());
        }

        $cookieGroups = $this->cookieService->getCookieGroupCollection(
            $cookieGroups,
            $salesChannelContext,
            $translate
        );

        return new CookieRouteResponse($cookieGroups);
    }
}
