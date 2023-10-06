<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Kernel;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('storefront')]
final class CacheWarmerTaskHandler
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly RouterInterface $router,
        private readonly RequestTransformerInterface $requestTransformer,
        private readonly CacheIdLoader $cacheIdLoader,
        private readonly CacheTagCollection $cacheTagCollection
    ) {
    }

    public function __invoke(WarmUpMessage $message): void
    {
        if ($this->cacheIdLoader->load() !== $message->getCacheId()) {
            return;
        }

        $kernel = $this->createHttpCacheKernel($message->getCacheId());

        foreach ($message->getParameters() as $parameters) {
            $url = rtrim($message->getDomain(), '/') . $this->router->generate($message->getRoute(), $parameters);

            $request = $this->requestTransformer->transform(Request::create($url));

            $kernel->handle($request);

            // the cache tag collection, collects all cache tags for a single request,
            // after the request handled, the collection has to be reset for the next request
            $this->cacheTagCollection->reset();
        }
    }

    private function createHttpCacheKernel(string $cacheId): HttpCache
    {
        $this->kernel->reboot(null, null, $cacheId);

        $store = $this->kernel->getContainer()->get(CacheStore::class);

        return new HttpCache($this->kernel, $store, null);
    }
}
