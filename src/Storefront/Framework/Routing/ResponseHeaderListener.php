<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\Annotation\NoStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

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
     * @deprecated tag:v6.5.0 - Will be removed, use onResponse() instead
     */
    public function __invoke(ResponseEvent $event): void
    {
        $this->onResponse($event);
    }

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
        /** @var RouteScope|list<string> $scopes */
        $scopes = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if ($scopes instanceof RouteScope) {
            $scopes = $scopes->getScopes();
        }

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
        if (!$request->attributes->has('_' . NoStore::ALIAS)) {
            return;
        }

        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setExpires(new \DateTime('@0'));
    }
}
