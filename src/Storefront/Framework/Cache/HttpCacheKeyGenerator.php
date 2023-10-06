<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('storefront')]
class HttpCacheKeyGenerator extends AbstractHttpCacheKeyGenerator
{
    /**
     * @param string[] $ignoredParameters
     *
     * @internal
     */
    public function __construct(
        private readonly string $cacheHash,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly array $ignoredParameters
    ) {
    }

    public function getDecorated(): AbstractHttpCacheKeyGenerator
    {
        throw new DecorationPatternException(self::class);
    }

    public function generate(Request $request): string
    {
        $uri = $this->getRequestUri($request) . $this->cacheHash;

        $event = new HttpCacheGenerateKeyEvent($request, 'md' . hash('sha256', $uri));

        $this->eventDispatcher->dispatch($event);

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
}
