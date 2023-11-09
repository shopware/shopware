<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - Class becomes internal and will be moved to core domain. Use events to manipulate cache key generation
 */
#[Package('core')]
class HttpCacheKeyGenerator extends AbstractHttpCacheKeyGenerator
{
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

    public function getDecorated(): AbstractHttpCacheKeyGenerator
    {
        throw new DecorationPatternException(self::class);
    }

    public function generate(Request $request): string
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return $this->deprecated($request);
        }

        $event = new HttpCacheKeyEvent($request);

        $event->add('uri', $this->getRequestUri($request));

        $this->addCookies($request, $event);

        $this->dispatcher->dispatch($event);

        $parts = $event->getParts();

        return 'http-cache-' . hash('sha256', implode('|', $parts));
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

        $baseUrl = $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL) ?? '';
        \assert(\is_string($baseUrl));

        return sprintf(
            '%s%s%s%s',
            $request->getSchemeAndHttpHost(),
            $baseUrl,
            $request->getPathInfo(),
            '?' . $params
        );
    }

    private function deprecated(Request $request): string
    {
        $uri = $this->getRequestUri($request) . $this->cacheHash;

        $event = new HttpCacheGenerateKeyEvent($request, 'md' . hash('sha256', $uri));

        $this->dispatcher->dispatch($event);

        $hash = $event->getHash();

        if ($request->cookies->has(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE)) {
            return 'http-cache-' . hash('sha256', $hash . '-' . $request->cookies->get(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE));
        }

        if ($request->cookies->has(CacheResponseSubscriber::CURRENCY_COOKIE)) {
            return 'http-cache-' . hash('sha256', $hash . '-' . $request->cookies->get(CacheResponseSubscriber::CURRENCY_COOKIE));
        }

        if ($request->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)) {
            return 'http-cache-' . hash('sha256', $hash . '-' . $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
        }

        return 'http-cache-' . $hash;
    }

    private function addCookies(Request $request, HttpCacheKeyEvent $event): void
    {
        // this will be changed within v6.6 lane that we only use the context cache cookie and developers can change the cookie instead
        // with this change, the reverse proxies are much easier to configure
        if ($request->cookies->has(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE)) {
            $event->add(
                CacheResponseSubscriber::CONTEXT_CACHE_COOKIE,
                $request->cookies->get(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE)
            );

            return;
        }

        if ($request->cookies->has(CacheResponseSubscriber::CURRENCY_COOKIE)) {
            $event->add(
                CacheResponseSubscriber::CURRENCY_COOKIE,
                $request->cookies->get(CacheResponseSubscriber::CURRENCY_COOKIE)
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
}
