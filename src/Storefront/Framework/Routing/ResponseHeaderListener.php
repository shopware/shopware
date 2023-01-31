<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @internal
 */
#[Package('storefront')]
class ResponseHeaderListener implements EventSubscriberInterface
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

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResponseEvent::class => ['onResponse', -10],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        /** @var list<string> $scopes */
        $scopes = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array(StorefrontRouteScope::ID, $scopes, true) && !$response instanceof StorefrontResponse) {
            return;
        }

        $this->manipulateStorefrontHeader($event->getRequest(), $response);
    }

    private function manipulateStorefrontHeader(Request $request, Response $response): void
    {
        $this->removeHeaders($response);
        $this->addNoStoreHeader($request, $response);
    }

    private function removeHeaders(Response $response): void
    {
        foreach (self::REMOVAL_HEADERS as $headerKey) {
            $response->headers->remove($headerKey);
        }
    }

    private function addNoStoreHeader(Request $request, Response $response): void
    {
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE)) {
            return;
        }

        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setExpires(new \DateTime('@0'));
    }
}
