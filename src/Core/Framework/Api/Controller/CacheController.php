<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Api\Event\InvalidateExpiredCacheRequestEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class CacheController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheClearer $cacheClearer,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly AdapterInterface $adapter,
        private readonly EntityIndexerRegistry $indexerRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route(
        path: '/api/_action/cache_info',
        name: 'api.action.cache.info',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:cache:info']],
        methods: [Request::METHOD_GET]
    )]
    public function info(): JsonResponse
    {
        return new JsonResponse([
            'environment' => $this->getParameter('kernel.environment'),
            'httpCache' => $this->container->get('parameter_bag')->has('shopware.http.cache.enabled') && $this->getParameter('shopware.http.cache.enabled'),
            'cacheAdapter' => $this->getUsedCache($this->adapter),
        ]);
    }

    #[Route(
        path: '/api/_action/index',
        name: 'api.action.cache.index',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_action_cache_index']],
        methods: [Request::METHOD_POST]
    )]
    public function index(RequestDataBag $dataBag): Response
    {
        $data = $dataBag->all();

        $skip = !empty($data['skip']) && \is_array($data['skip']) ? array_values($data['skip']) : [];
        $only = !empty($data['only']) && \is_array($data['only']) ? array_values($data['only']) : [];

        $this->indexerRegistry->sendFullIndexingMessage($skip, $only);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/cache',
        name: 'api.action.cache.delete',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:clear:cache']],
        methods: [Request::METHOD_DELETE]
    )]
    public function clearCache(): Response
    {
        $this->cacheClearer->clear();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/cache-delayed',
        name: 'api.action.cache.delete-delayed',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:clear:cache']],
        methods: [Request::METHOD_DELETE]
    )]
    public function clearDelayedCache(Request $request): Response
    {
        $this->cacheInvalidator->invalidateExpired();

        $this->eventDispatcher->dispatch(new InvalidateExpiredCacheRequestEvent($request));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/cleanup',
        name: 'api.action.cache.cleanup',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:clear:cache']],
        methods: [Request::METHOD_DELETE]
    )]
    public function clearOldCacheFolders(): Response
    {
        $this->cacheClearer->scheduleCacheFolderCleanup();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/container_cache',
        name: 'api.action.container-cache.delete',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:clear:cache']],
        methods: [Request::METHOD_DELETE]
    )]
    public function clearContainerCache(): Response
    {
        $this->cacheClearer->clearContainerCache();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function getUsedCache(AdapterInterface $adapter): string
    {
        if ($adapter instanceof TagAwareAdapter || $adapter instanceof TraceableAdapter) {
            // Do not declare function as static
            $func = \Closure::bind(fn () => $adapter->pool, $adapter, $adapter::class);

            $adapter = $func();
        }

        if ($adapter instanceof TraceableAdapter) {
            return $this->getUsedCache($adapter);
        }

        $parts = explode('\\', $adapter::class);

        return str_replace('Adapter', '', array_last($parts));
    }
}
