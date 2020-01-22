<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessageSender;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Util\Random;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class CacheController extends AbstractController
{
    /**
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var IndexerMessageSender
     */
    private $indexerMessageSender;

    /**
     * @var CacheWarmer
     */
    private $cacheWarmer;

    public function __construct(
        CacheClearer $cache,
        AdapterInterface $adapter,
        IndexerMessageSender $indexerMessageSender,
        CacheWarmer $cacheWarmer
    ) {
        $this->cache = $cache;
        $this->adapter = $adapter;
        $this->indexerMessageSender = $indexerMessageSender;
        $this->cacheWarmer = $cacheWarmer;
    }

    /**
     * @Route("/api/v{version}/_action/cache_info", name="api.action.cache.info", methods={"GET"})
     */
    public function info(): JsonResponse
    {
        return new JsonResponse([
            'environment' => getenv('APP_ENV'),
            'httpCache' => (bool) getenv('SHOPWARE_HTTP_CACHE_ENABLED'),
            'cacheAdapter' => $this->getUsedCache($this->adapter),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/index", name="api.action.cache.index", methods={"POST"})
     */
    public function index(): JsonResponse
    {
        $this->indexerMessageSender->partial(new \DateTime());

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_action/cache_warmup", name="api.action.cache.delete_and_warmup", methods={"DELETE"})
     */
    public function clearCacheAndScheduleWarmUp(): JsonResponse
    {
        $this->cacheWarmer->warmUp(Random::getAlphanumericString(32));

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_action/cache", name="api.action.cache.delete", methods={"DELETE"})
     */
    public function clearCache(): JsonResponse
    {
        $this->cache->clear();

        return new JsonResponse();
    }

    private function getUsedCache(AdapterInterface $adapter): string
    {
        if ($adapter instanceof TagAwareAdapter || $adapter instanceof TraceableAdapter) {
            $func = \Closure::bind(function () use ($adapter) {
                return $adapter->pool;
            }, $adapter, get_class($adapter));

            $adapter = $func();
        }

        if ($adapter instanceof TraceableAdapter) {
            return $this->getUsedCache($adapter);
        }

        $name = get_class($adapter);
        $parts = explode('\\', $name);
        $name = str_replace('Adapter', '', end($parts));

        return $name;
    }
}
