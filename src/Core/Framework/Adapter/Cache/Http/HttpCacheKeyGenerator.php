<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class HttpCacheKeyGenerator
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed as it is already part of the cache cookie
     */
    final public const CURRENCY_COOKIE = 'sw-currency';
    final public const CONTEXT_CACHE_COOKIE = 'sw-cache-hash';
    /**
     * @deprecated tag:v6.8.0 - Will be removed use cache cookie event instead
     */
    final public const SYSTEM_STATE_COOKIE = 'sw-states';
    /**
     * @deprecated tag:v6.8.0 - Will be removed use cache cookie event instead
     */
    final public const INVALIDATION_STATES_HEADER = 'sw-invalidation-states';
    /**
     * Header to hint reverse proxy that cache was dynamically bypassed (and url still can be cached for other requests).
     * This allows decreasing TTLs for `hit-for-pass` objects in reverse proxies for such cases, while keeping higher TTLs
     * for generally not-cacheable pages.
     */
    final public const HEADER_DYNAMIC_CACHE_BYPASS = 'sw-dynamic-cache-bypass';

    /**
     * Virtual path of the "domain"
     *
     * @example
     * - `/de`
     * - `/en`
     * - {empty} - the virtual path is optional
     */
    private const SALES_CHANNEL_BASE_URL = 'sw-sales-channel-base-url';

    /**
     * @param string[] $ignoredParameters
     *
     * @internal
     */
    public function __construct(
        private readonly string $cacheHash,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $ignoredParameters
    ) {
    }

    /**
     * Generates a cache key for the given request.
     * This method should return a key that must only depend on a
     * normalized version of the request URI.
     * If the same URI can have more than one representation, based on some
     * headers, use a `vary` header to indicate them, and each representation will
     * be stored independently under the same cache key.
     *
     * @return CacheKey The cache key for the given request
     */
    public function generate(Request $request, ?Response $response = null): CacheKey
    {
        $event = new HttpCacheKeyEvent($request);

        $event->add('uri', $this->getRequestUri($request));

        $event->add('hash', $this->cacheHash);

        $this->addVariantHeaders($request, $event);

        $this->addCookies($request, $response, $event);

        $this->dispatcher->dispatch($event);

        $parts = $event->getParts();

        return new CacheKey(
            'http-cache-' . Hasher::hash(implode('|', $parts)),
            $event->isCacheable
        );
    }

    private function getRequestUri(Request $request): string
    {
        $params = $request->query->all();
        foreach (array_keys($params) as $key) {
            if (\in_array($key, $this->ignoredParameters, true)) {
                unset($params[$key]);
            }
        }
        ksort($params);
        $params = http_build_query($params);

        $baseUrl = $request->attributes->get(self::SALES_CHANNEL_BASE_URL) ?? '';
        \assert(\is_string($baseUrl));

        return \sprintf(
            '%s%s%s%s',
            $request->getSchemeAndHttpHost(),
            $baseUrl,
            $request->getPathInfo(),
            '?' . $params
        );
    }

    /**
     * Shopware response may be influenced by headers, not only by domain URL or cookies.
     * Such headers should be included in the cache key calculation.
     * Requests that do not carry these headers are not affected.
     */
    private function addVariantHeaders(Request $request, HttpCacheKeyEvent $event): void
    {
        foreach (HttpCacheVariantHeaders::HEADERS as $header) {
            // the cache hash needs cookie precedence and is handled in addCookies()
            if ($header === self::CONTEXT_CACHE_COOKIE) {
                continue;
            }

            // Values are matched case-sensitive: sw-access-key is a case-sensitive and language/currency ids
            // must be lowercase hex, so any other casing is a different (and invalid) request.
            // This behavior is similar to how headers mentioned in Vary are handled by reverse proxies.
            $value = (string) $request->headers->get($header, '');
            if ($value !== '') {
                $event->add($header, $value);
            }
        }
    }

    private function addCookies(Request $request, ?Response $response, HttpCacheKeyEvent $event): void
    {
        // The response cookie carries the server-computed hash on write and stays authoritative.
        // On the request side the header wins over the cookie, matching the poisoning guard in
        // CacheResponseSubscriber and priority in the Shopware reverse proxies configs.
        $cacheCookie = $this->getResponseCookieValue($response, self::CONTEXT_CACHE_COOKIE)
            ?? $request->headers->get(self::CONTEXT_CACHE_COOKIE)
            ?? $this->getRequestCookieValue($request, self::CONTEXT_CACHE_COOKIE);

        if ($cacheCookie) {
            $event->add(
                self::CONTEXT_CACHE_COOKIE,
                $cacheCookie
            );

            if ($cacheCookie === HttpCacheCookieEvent::NOT_CACHEABLE) {
                $event->isCacheable = false;
            }

            return;
        }

        /** @deprecated tag:v6.8.0 - Currency cookie will be removed */
        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
            if ($currencyCookie = $this->getCookieValue($request, $response, self::CURRENCY_COOKIE)) {
                $event->add(
                    self::CURRENCY_COOKIE,
                    $currencyCookie
                );

                return;
            }
        }

        if ($request->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)) {
            $event->add(
                SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID,
                $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)
            );
        }
    }

    /**
     * get Cookie value, if exists use response cookie value instead of request cookie value as request cookies can be overwritten by the client
     */
    private function getCookieValue(Request $request, ?Response $response, string $cookieName): ?string
    {
        return $this->getResponseCookieValue($response, $cookieName)
            ?? $this->getRequestCookieValue($request, $cookieName);
    }

    private function getResponseCookieValue(?Response $response, string $cookieName): ?string
    {
        if (!$response) {
            return null;
        }

        $cookie = Cookie::create($cookieName);

        $responseCookies = $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);

        $responseCookie = $responseCookies[$cookie->getDomain() ?? ''][$cookie->getPath()][$cookieName] ?? null;

        // if the response contains the cookie, we use it instead of the request value
        // as the request value can be overwritten by the client;
        // however the response cookie is only set if it differs from the request cookie,
        // so callers need to fall back to the request when the response cookie is not set
        return $responseCookie?->getValue();
    }

    private function getRequestCookieValue(Request $request, string $cookieName): ?string
    {
        if ($request->cookies->has($cookieName)) {
            return (string) $request->cookies->get($cookieName);
        }

        return null;
    }
}
