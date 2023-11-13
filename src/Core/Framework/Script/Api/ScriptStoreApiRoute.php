<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('core')]
class ScriptStoreApiRoute
{
    final public const INVALIDATION_STATES_HEADER = 'sw-invalidation-states';

    public function __construct(
        private readonly ScriptExecutor $executor,
        private readonly ScriptResponseEncoder $scriptResponseEncoder,
        private readonly TagAwareAdapterInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(path: '/store-api/script/{hook}', name: 'store-api.script_endpoint', methods: ['GET', 'POST'], requirements: ['hook' => '.+'])]
    public function execute(string $hook, Request $request, SalesChannelContext $context): Response
    {
        //  blog/update =>  blog-update
        $hookName = \str_replace('/', '-', $hook);

        $hook = new StoreApiHook($hookName, $request->request->all(), $request->query->all(), $context);

        $cacheKey = null;
        if ($request->isMethodCacheable()) {
            /** @var StoreApiCacheKeyHook $cacheKeyHook */
            $cacheKeyHook = $hook->getFunction(StoreApiCacheKeyHook::FUNCTION_NAME);

            $this->executor->execute($cacheKeyHook);

            $cacheKey = $cacheKeyHook->getCacheKey();
        }

        $cachedResponse = $this->readFromCache($cacheKey, $context, $request);

        if ($cachedResponse) {
            return $cachedResponse;
        }

        /** @var StoreApiResponseHook $responseHook */
        $responseHook = $hook->getFunction(StoreApiResponseHook::FUNCTION_NAME);
        // hook: store-api-{hook}
        $this->executor->execute($responseHook);

        $fields = new ResponseFields(
            $request->get('includes', [])
        );

        $symfonyResponse = $this->scriptResponseEncoder->encodeToSymfonyResponse(
            $responseHook->getScriptResponse(),
            $fields,
            \str_replace('-', '_', 'store_api_' . $hookName . '_response')
        );

        $cacheConfig = $responseHook->getScriptResponse()->getCache();
        if ($cacheKey && $cacheConfig->isEnabled()) {
            $this->storeResponse($cacheKey, $cacheConfig, $symfonyResponse);
        }

        return $symfonyResponse;
    }

    private function readFromCache(?string $cacheKey, SalesChannelContext $context, Request $request): ?Response
    {
        if (!$cacheKey) {
            return null;
        }

        $item = $this->cache->getItem($cacheKey);

        try {
            if (!$item->isHit() || !$item->get()) {
                $this->logger->info('cache-miss: ' . $request->getPathInfo());

                return null;
            }

            /** @var Response $response */
            $response = CacheCompressor::uncompress($item);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return null;
        }

        $invalidationStates = explode(',', (string) $response->headers->get(self::INVALIDATION_STATES_HEADER));
        if ($context->hasState(...$invalidationStates)) {
            $this->logger->info('cache-miss: ' . $request->getPathInfo());

            return null;
        }

        $response->headers->remove(self::INVALIDATION_STATES_HEADER);

        $this->logger->info('cache-hit: ' . $request->getPathInfo());

        return $response;
    }

    private function storeResponse(string $cacheKey, ResponseCacheConfiguration $cacheConfig, Response $symfonyResponse): void
    {
        $item = $this->cache->getItem($cacheKey);

        // add the header only for the response in cache and remove the header before the response is sent
        $symfonyResponse->headers->set(self::INVALIDATION_STATES_HEADER, implode(',', $cacheConfig->getInvalidationStates()));
        $item = CacheCompressor::compress($item, $symfonyResponse);
        $symfonyResponse->headers->remove(self::INVALIDATION_STATES_HEADER);

        $item->tag($cacheConfig->getCacheTags());
        $item->expiresAfter($cacheConfig->getMaxAge());

        $this->cache->save($item);
    }
}
