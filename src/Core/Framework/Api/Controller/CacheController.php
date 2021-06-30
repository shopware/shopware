<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class CacheController extends AbstractController
{
    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var CacheWarmer|null
     */
    private $cacheWarmer;

    /**
     * @var EntityIndexerRegistry
     */
    private $indexerRegistry;

    public function __construct(
        CacheClearer $cacheClearer,
        AdapterInterface $adapter,
        ?CacheWarmer $cacheWarmer,
        EntityIndexerRegistry $indexerRegistry
    ) {
        $this->cacheClearer = $cacheClearer;
        $this->adapter = $adapter;
        $this->cacheWarmer = $cacheWarmer;
        $this->indexerRegistry = $indexerRegistry;
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Get(
     *     path="/_action/cache_info",
     *     summary="Get cache information",
     *     description="Get information about the cache configuration",
     *     operationId="info",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="200",
     *         description="Information about the cache state.",
     *         @OA\JsonContent(
     *               @OA\Property(
     *                  property="environment",
     *                  description="The active environment.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="httpCache",
     *                  description="State of the HTTP cache.",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="cacheAdapter",
     *                  description="The active cache adapter.",
     *                  type="string"
     *              )
     *         )
     *     )
     * )
     * @Route("/api/_action/cache_info", name="api.action.cache.info", methods={"GET"})
     * @Acl({"system:cache:info"})
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
     * @Since("6.2.0.0")
     * @OA\Post(
     *     path="/_action/index",
     *     summary="Run indexer",
     *     description="Runs all registered indexer in the shop asynchronously.",
     *     operationId="index",
     *     tags={"Admin API", "System Operations"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="skip",
     *                 description="Array of indexers/updaters to be skipped.",
     *                 type="array"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating that the indexing progress startet."
     *     )
     * )
     * @Route("/api/_action/index", name="api.action.cache.index", methods={"POST"})
     * @Acl({"api_action_cache_index"})
     */
    public function index(RequestDataBag $dataBag): Response
    {
        $data = $dataBag->all();
        $skip = !empty($data['skip']) && \is_array($data['skip']) ? $data['skip'] : [];

        $this->indexerRegistry->sendIndexingMessage([], $skip);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Delete(
     *     path="/_action/cache_warmup",
     *     summary="Clear and warm up caches",
     *     description="After the cache has been cleared, new cache entries are generated asynchronously.",
     *     operationId="clearCacheAndScheduleWarmUp",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating that the cache has been cleared and generation of new cache has started."
     *     )
     * )
     * @Route("/api/_action/cache_warmup", name="api.action.cache.delete_and_warmup", methods={"DELETE"})
     * @Acl({"system:clear:cache"})
     */
    public function clearCacheAndScheduleWarmUp(): Response
    {
        if ($this->cacheWarmer === null) {
            throw new \RuntimeException('Storefront is not installed');
        }

        $this->cacheWarmer->warmUp(Random::getAlphanumericString(32));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Delete(
     *     path="/_action/cache",
     *     summary="Clear caches",
     *     description="The cache is immediately cleared synchronously for all used adapters.",
     *     operationId="clearCache",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating that the cache has been cleared."
     *     )
     * )
     * @Route("/api/_action/cache", name="api.action.cache.delete", methods={"DELETE"})
     * @Acl({"system:clear:cache"})
     */
    public function clearCache(): Response
    {
        $this->cacheClearer->clear();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Delete(
     *     path="/_action/cleanup",
     *     summary="Clear old cache folders",
     *     description="Removes cache folders that are not needed anymore.",
     *     operationId="clearOldCacheFolders",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating that the cleanup finished."
     *     )
     * )
     * @Route("/api/_action/cleanup", name="api.action.cache.cleanup", methods={"DELETE"})
     * @Acl({"system:clear:cache"})
     */
    public function clearOldCacheFolders(): Response
    {
        $this->cacheClearer->scheduleCacheFolderCleanup();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Delete(
     *     path="/_action/container_cache",
     *     summary="Clear container caches",
     *     description="The container cache is immediately cleared synchronously.",
     *     operationId="clearContainerCache",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="204",
     *         description="Returns a no content response indicating that the container cache is cleared."
     *     )
     * )
     * @Route("/api/_action/container_cache", name="api.action.container-cache.delete", methods={"DELETE"})
     * @Acl({"system:clear:cache"})
     */
    public function clearContainerCache(): Response
    {
        $this->cacheClearer->clearContainerCache();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function getUsedCache(AdapterInterface $adapter): string
    {
        if ($adapter instanceof TagAwareAdapter || $adapter instanceof TraceableAdapter) {
            // Do not declare function as static
            $func = \Closure::bind(function () use ($adapter) {
                return $adapter->pool;
            }, $adapter, \get_class($adapter));

            $adapter = $func();
        }

        if ($adapter instanceof TraceableAdapter) {
            return $this->getUsedCache($adapter);
        }

        $name = \get_class($adapter);
        $parts = explode('\\', $name);
        $name = str_replace('Adapter', '', end($parts));

        return $name;
    }
}
