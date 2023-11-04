<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[Package('core')]
class RouteParamsCleanupListener
{
    private const CLEANUP_PARAMETERS = [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE,
        PlatformRequest::ATTRIBUTE_CAPTCHA,
        PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED,
        PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST,
        PlatformRequest::ATTRIBUTE_ACL,
    ];

    public function __invoke(RequestEvent $event): void
    {
        $routeParams = $event->getRequest()->attributes->get('_route_params', []);

        if ($routeParams) {
            foreach (self::CLEANUP_PARAMETERS as $param) {
                if (isset($routeParams[$param])) {
                    unset($routeParams[$param]);
                }
            }
        }

        $event->getRequest()->attributes->set('_route_params', $routeParams);
    }
}
