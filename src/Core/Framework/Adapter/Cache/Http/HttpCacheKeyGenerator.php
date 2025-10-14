<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
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
    final public const CURRENCY_COOKIE = 'sw-currency';
    final public const CONTEXT_CACHE_COOKIE = 'sw-cache-hash';
    final public const SYSTEM_STATE_COOKIE = 'sw-states';
    final public const INVALIDATION_STATES_HEADER = 'sw-invalidation-states';
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
     * @return string A key for the given request
     */
    public function generate(Request $request, ?Response $response = null): string
    {
        $event = new HttpCacheKeyEvent($request);

        $event->add('uri', $this->getRequestUri($request));

        $event->add('hash', $this->cacheHash);

        $this->addCookies($request, $response, $event);

        $this->dispatcher->dispatch($event);

        $parts = $event->getParts();

        return 'http-cache-' . Hasher::hash(implode('|', $parts));
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

    private function addCookies(Request $request, ?Response $response, HttpCacheKeyEvent $event): void
    {
        // this will be changed within v6.6 lane that we only use the context cache cookie and developers can change the cookie instead
        // with this change, the reverse proxies are much easier to configure
        if ($cacheCookie = $this->getCookieValue($request, $response, self::CONTEXT_CACHE_COOKIE)) {
            $event->add(
                self::CONTEXT_CACHE_COOKIE,
                $cacheCookie
            );

            return;
        }

        if ($currencyCookie = $this->getCookieValue($request, $response, self::CURRENCY_COOKIE)) {
            $event->add(
                self::CURRENCY_COOKIE,
                $currencyCookie
            );

            return;
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
        if ($response) {
            $cookie = Cookie::create($cookieName);

            $responseCookies = $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);

            $responseCookie = $responseCookies[$cookie->getDomain()][$cookie->getPath()][$cookieName] ?? null;

            if ($responseCookie) {
                // if the response contains the cookie, we use it instead of the request cookie
                // as the request cookie can be overwritten by the client
                // however the response cookie is only set if it differs from the request cookie,
                // so we need to fall back to the request cookie when the response cookie is not set
                return $responseCookie->getValue();
            }
        }

        if ($request->cookies->has($cookieName)) {
            return (string) $request->cookies->get($cookieName);
        }

        return null;
    }
}
