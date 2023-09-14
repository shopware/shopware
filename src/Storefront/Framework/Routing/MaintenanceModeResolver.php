<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver as CoreMaintenanceModeResolver;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Package('storefront')]
class MaintenanceModeResolver
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var CoreMaintenanceModeResolver
     */
    protected $maintenanceModeResolver;

    /**
     * @internal
     */
    public function __construct(RequestStack $requestStack, CoreMaintenanceModeResolver $maintenanceModeResolver)
    {
        $this->requestStack = $requestStack;
        $this->maintenanceModeResolver = $maintenanceModeResolver;
    }

    /**
     * shouldRedirect returns true, when the given request should be redirected to the maintenance page.
     * This would be the case, for example, when the maintenance mode is active and the client's IP address
     * is not listed in the maintenance mode whitelist.
     */
    public function shouldRedirect(Request $request): bool
    {
        return $this->isSalesChannelRequest()
            && !$request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE)
            && !$this->isXmlHttpRequest($request)
            && !$this->isErrorControllerRequest($request)
            && $this->isMaintenanceRequest($request);
    }

    /**
     * shouldRedirectToShop returns true, when the given request to the maintenance page should be redirected to the shop.
     * This would be the case, for example, when the maintenance mode is not active or if it is active
     * the client's IP address is listed in the maintenance mode whitelist.
     */
    public function shouldRedirectToShop(Request $request): bool
    {
        return !$this->isXmlHttpRequest($request)
            && !$this->isErrorControllerRequest($request)
            && !$this->isMaintenanceRequest($request);
    }

    public function shouldBeCached(Request $request): bool
    {
        return !$this->isMaintenanceModeActive() || !$this->isClientAllowed($request);
    }

    /**
     * isMaintenanceRequest returns true, when the maintenance mode is active and the client's IP address
     * is not listed in the maintenance mode whitelist.
     */
    public function isMaintenanceRequest(Request $request): bool
    {
        return $this->isMaintenanceModeActive() && !$this->isClientAllowed($request);
    }

    private function isSalesChannelRequest(): bool
    {
        $main = $this->requestStack->getMainRequest();

        return (bool) $main?->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST);
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

    private function isMaintenanceModeActive(): bool
    {
        $main = $this->requestStack->getMainRequest();

        return (bool) $main?->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE);
    }

    private function isClientAllowed(Request $request): bool
    {
        $main = $this->requestStack->getMainRequest();
        $whitelist = $main?->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST) ?? '';

        /** @var string[] $allowedIps */
        $allowedIps = Json::decodeToList((string) $whitelist);

        return $this->maintenanceModeResolver->isClientAllowed($request, $allowedIps);
    }
}
