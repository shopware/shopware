<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MaintenanceModeResolver
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * shouldRedirect returns true, when the given request should be redirected to the maintenance page.
     * This would be the case, for example, when the maintenance mode is active and the client's IP address
     * is not listed in the maintenance mode whitelist.
     */
    public function shouldRedirect(Request $request): bool
    {
        return $this->isSalesChannelRequest($this->requestStack->getMasterRequest())
            && !$this->isMaintenancePageRequest($request)
            && !$this->isXmlHttpRequest($request)
            && !$this->isErrorControllerRequest($request)
            && $this->isMaintenanceModeActive($this->requestStack->getMasterRequest())
            && !$this->isClientAllowed($request);
    }

    public function shouldRedirectToShop(Request $request): bool
    {
        return !$this->isXmlHttpRequest($request)
            && !$this->isErrorControllerRequest($request)
            && (!$this->isMaintenanceModeActive($this->requestStack->getMasterRequest())
                || $this->isClientAllowed($request));
    }

    private function isSalesChannelRequest(?Request $master): bool
    {
        if (!$master) {
            return false;
        }

        return (bool) $master->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST);
    }

    private function isMaintenancePageRequest(Request $request): bool
    {
        if ($request->attributes->getBoolean(SalesChannelRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE)) {
            return true;
        }

        $route = $request->attributes->get('_route');

        if (!$route) {
            return false;
        }

        // @deprecated tag:v6.4.0 - Use defaults={"allow_maintenance"=true} in Route definition instead
        return mb_strpos($route, 'frontend.maintenance') !== false;
    }

    private function isXmlHttpRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest();
    }

    private function isErrorControllerRequest(Request $request): bool
    {
        return $request->attributes->get('_route') === null
            && $request->attributes->get('_controller') === 'error_controller';
    }

    private function isMaintenanceModeActive(?Request $master): bool
    {
        if (!$master) {
            return false;
        }

        return (bool) $master->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE);
    }

    private function isClientAllowed(Request $request): bool
    {
        return IpUtils::checkIp(
            (string) $request->getClientIp(),
            $this->getMaintenanceWhitelist($this->requestStack->getMasterRequest())
        );
    }

    private function getMaintenanceWhitelist(?Request $master): array
    {
        if (!$master) {
            return [];
        }

        $whitelist = $master->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST);

        if (!$whitelist) {
            return [];
        }

        return json_decode($whitelist, true) ?? [];
    }
}
