<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Catches ProductException::pageOutOfRange thrown by PagingListingProcessor and
 * rewrites it to a 301 redirect to the canonical URL (with the `p` query parameter
 * stripped) for Storefront requests. Store API requests are not handled here and
 * surface as 404.
 *
 * @internal
 */
#[Package('discovery')]
class ProductListingPageOutOfRangeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Priority 10 keeps us ahead of NotFoundSubscriber (-100) — which would otherwise
        // render the 404 page — while still sitting below any plugin listener that wants to
        // produce its own response for this exception.
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // A higher-priority listener (or a plugin) may have already produced a response
        // for this exception (e.g. a custom branded soft-404 page). Don't clobber it.
        if ($event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof ProductException || !$throwable->is(ProductException::LISTING_PAGE_OUT_OF_RANGE)) {
            return;
        }

        // The 301 is intentionally cacheable by the reverse proxy — the cache key already
        // includes the full URL (so /Foo/?p=99 and /Foo/?p=2 are separate entries) and
        // the sales-channel cache hash (so different rule contexts get different entries).
        $event->setResponse(new RedirectResponse($this->buildRedirectTarget($request), Response::HTTP_MOVED_PERMANENTLY));
    }

    private function buildRedirectTarget(Request $request): string
    {
        $originalUri = $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI);

        if (!\is_string($originalUri) || $originalUri === '') {
            $originalUri = $request->getRequestUri();
        }

        $parts = parse_url($originalUri);
        $path = \is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : '/';

        $queryString = \is_array($parts) ? ($parts['query'] ?? '') : '';
        $params = [];

        if (\is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $params);
        }
        unset($params['p']);

        if ($params === []) {
            return $path;
        }

        return $path . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }
}
