<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Adapter\Cache\Http;

use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;

/**
 * @internal
 */
#[Package('framework')]
readonly class AdministrationCacheControlListener
{
    public function __invoke(BeforeCacheControlEvent $event): void
    {
        if (!$this->isAdministrationRequest($event)) {
            return;
        }

        $event->skipCacheControl();
    }

    private function isAdministrationRequest(BeforeCacheControlEvent $event): bool
    {
        $response = $event->response;

        // Check if the response has been marked as an administration response
        if ($response->headers->get(AdministrationController::CACHE_ID_HEADER) === AdministrationController::CACHE_ID_ADMINISTRATION) {
            return true;
        }

        $request = $event->request;

        // Check route scope attribute
        if (\in_array(
            AdministrationRouteScope::ID,
            (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []),
            true
        )) {
            return true;
        }

        // Fallback: Check if the route name starts with 'administration.'
        $routeName = $request->attributes->get('_route');
        if (\is_string($routeName) && \str_starts_with($routeName, 'administration.')) {
            return true;
        }

        return false;
    }
}
