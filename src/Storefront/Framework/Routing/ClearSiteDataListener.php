<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Sends the `Clear-Site-Data` header on storefront responses that opted in via
 * `PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA`.
 *
 * Without configured directives this listener is a no-op.
 *
 * @internal
 */
#[Package('framework')]
class ClearSiteDataListener implements EventSubscriberInterface
{
    /**
     * Other directives (`executionContexts`, `clientHints`, `*`) are not supported,
     * as browsers implement them inconsistently or not at all.
     */
    public const ALLOWED_DIRECTIVES = ['cache', 'cookies', 'storage'];

    /**
     * A `Clear-Site-Data` wipe is origin wide, so it must not be triggerable cross-site.
     * `none` covers bookmarks and directly entered URLs.
     */
    private const ALLOWED_FETCH_SITES = ['same-origin', 'same-site', 'none'];

    /**
     * @param list<string> $directives
     */
    public function __construct(private readonly array $directives)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRouteScope::ID . '.scope.response' => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($this->directives === [] || !$event->isMainRequest()) {
            return;
        }

        if (!$this->isEligible($event->getRequest())) {
            return;
        }

        $event->getResponse()->headers->set(
            'Clear-Site-Data',
            implode(', ', array_map(static fn (string $directive): string => '"' . $directive . '"', $this->directives))
        );
    }

    private function isEligible(Request $request): bool
    {
        if (!$request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA)) {
            return false;
        }

        // cacheable responses must not carry the header; `_httpCache` is not always
        // a bool, so it must not be read via `getBoolean()`
        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE)) {
            return false;
        }

        return $this->isDeliberateNavigation($request);
    }

    /**
     * Only a top-level navigation the visitor triggered themselves may wipe the origin.
     * A subresource (`<img>`, `fetch()`, an iframe) or a speculative prefetch must not
     * be enough to clear the visitor's browser data.
     */
    private function isDeliberateNavigation(Request $request): bool
    {
        if (!\in_array($request->headers->get('Sec-Fetch-Site'), self::ALLOWED_FETCH_SITES, true)) {
            return false;
        }

        if ($request->headers->get('Sec-Fetch-Mode') !== 'navigate') {
            return false;
        }

        if ($request->headers->get('Sec-Fetch-Dest') !== 'document') {
            return false;
        }

        return !$request->headers->has('Sec-Purpose');
    }
}
