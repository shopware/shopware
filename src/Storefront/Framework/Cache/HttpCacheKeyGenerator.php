<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class HttpCacheKeyGenerator extends AbstractHttpCacheKeyGenerator
{
    private string $cacheHash;

    private EventDispatcherInterface $eventDispatcher;

    private array $ignoredParameters;

    public function __construct(
        string $cacheHash,
        EventDispatcherInterface $eventDispatcher,
        array $ignoredParameters
    ) {
        $this->cacheHash = $cacheHash;
        $this->eventDispatcher = $eventDispatcher;
        $this->ignoredParameters = $ignoredParameters;
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
            return hash('sha256', $hash . '-' . $request->cookies->get(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE));
        }

        if ($request->cookies->has(CacheResponseSubscriber::CURRENCY_COOKIE)) {
            return hash('sha256', $hash . '-' . $request->cookies->get(CacheResponseSubscriber::CURRENCY_COOKIE));
        }

        if ($request->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)) {
            return hash('sha256', $hash . '-' . $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
        }

        return $hash;
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

        return sprintf(
            '%s%s%s%s',
            $request->getSchemeAndHttpHost(),
            $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL),
            $request->getPathInfo(),
            '?' . $params
        );
    }
}
