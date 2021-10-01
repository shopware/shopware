<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ResponseHeaderListener
{
    private const HEADERS = [
        PlatformRequest::HEADER_VERSION_ID,
        PlatformRequest::HEADER_LANGUAGE_ID,
        PlatformRequest::HEADER_CONTEXT_TOKEN,
    ];

    public function __invoke(ResponseEvent $event): void
    {
        /** @var RouteScope|null $routeScope */
        $routeScope = $event->getRequest()->attributes->get('_routeScope');

        if ($routeScope === null || !$routeScope->hasScope('storefront')) {
            return;
        }

        foreach (self::HEADERS as $headerKey) {
            $event->getResponse()->headers->remove($headerKey);
        }

        $event->getResponse()->headers->remove('Access-Control-Allow-Origin');
        $event->getResponse()->headers->remove('Access-Control-Allow-Methods');
        $event->getResponse()->headers->remove('Access-Control-Allow-Headers');
        $event->getResponse()->headers->remove('Access-Control-Expose-Headers');
    }
}
