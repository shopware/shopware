<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\Annotation\NoStore;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ResponseHeaderListener
{
    private const REMOVAL_HEADERS = [
        PlatformRequest::HEADER_VERSION_ID,
        PlatformRequest::HEADER_LANGUAGE_ID,
        PlatformRequest::HEADER_CONTEXT_TOKEN,
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Methods',
        'Access-Control-Allow-Headers',
        'Access-Control-Expose-Headers',
    ];

    public function __invoke(ResponseEvent $event): void
    {
        /** @var RouteScope|null $routeScope */
        $routeScope = $event->getRequest()->attributes->get('_routeScope');

        if ($routeScope === null || !$routeScope->hasScope('storefront')) {
            return;
        }

        $this->removeHeaders($event);
        $this->addNoStoreHeader($event);
    }

    private function removeHeaders(ResponseEvent $event): void
    {
        foreach (self::REMOVAL_HEADERS as $headerKey) {
            $event->getResponse()->headers->remove($headerKey);
        }
    }

    private function addNoStoreHeader(ResponseEvent $event): void
    {
        if (!$event->getRequest()->attributes->has('_' . NoStore::ALIAS)) {
            return;
        }

        $event->getResponse()->setMaxAge(0);
        $event->getResponse()->headers->addCacheControlDirective('no-cache');
        $event->getResponse()->headers->addCacheControlDirective('no-store');
        $event->getResponse()->headers->addCacheControlDirective('must-revalidate');
        $event->getResponse()->setExpires(new \DateTime('@0'));
    }
}
