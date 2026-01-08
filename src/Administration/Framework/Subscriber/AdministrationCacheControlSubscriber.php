<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Subscriber;

use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class AdministrationCacheControlSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCacheControlEvent::class => 'onBeforeCacheControl',
        ];
    }

    public function onBeforeCacheControl(BeforeCacheControlEvent $event): void
    {
        if (!$this->isAdministrationRequest($event)) {
            return;
        }

        $event->skipCacheControl();
    }

    private function isAdministrationRequest(BeforeCacheControlEvent $event): bool
    {
        $response = $event->getResponse();

        // Check if the response has been marked as an administration response
        if ($response->headers->get('X-Shopware-Cache-Id') === 'administration') {
            return true;
        }

        $request = $event->getRequest();

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
