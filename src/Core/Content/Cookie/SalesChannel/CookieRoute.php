<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('framework')]
class CookieRoute extends AbstractCookieRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CookieProvider $cookieProvider,
        private readonly CookieService $cookieService,
    ) {
    }

    public function getDecorated(): AbstractCookieRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cookie-groups', name: 'store-api.cookie.groups', methods: [Request::METHOD_GET])]
    public function getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieRouteResponse
    {
        $translate = $request->query->getBoolean('translate', true); // Default to true for Store API consumers

        $cookieGroups = $this->cookieProvider->getCookieGroups($salesChannelContext);

        $this->cookieService->removeCookieGroupsWithoutCookies($cookieGroups);

        if ($translate) {
            $this->cookieService->translateCookieGroups($cookieGroups);
        }

        return new CookieRouteResponse($cookieGroups);
    }
}
