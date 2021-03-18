<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;

class CacheWarmer extends AbstractMessageHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    private $domainRepository;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var CacheRouteWarmerRegistry
     */
    private $registry;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RequestTransformerInterface
     */
    private $requestTransformer;

    /**
     * @var CacheIdLoader
     */
    private $cacheIdLoader;

    /**
     * @var \Shopware\Core\Framework\Adapter\Cache\CacheTagCollection
     */
    private $cacheTagCollection;

    public function __construct(
        EntityRepositoryInterface $domainRepository,
        MessageBusInterface $bus,
        CacheRouteWarmerRegistry $registry,
        Kernel $kernel,
        RouterInterface $router,
        RequestTransformerInterface $requestTransformer,
        CacheIdLoader $cacheIdLoader,
        CacheTagCollection $cacheTagCollection
    ) {
        $this->domainRepository = $domainRepository;
        $this->bus = $bus;
        $this->registry = $registry;
        $this->kernel = $kernel;
        $this->router = $router;
        $this->requestTransformer = $requestTransformer;
        $this->cacheIdLoader = $cacheIdLoader;
        $this->cacheTagCollection = $cacheTagCollection;
    }

    public static function getHandledMessages(): iterable
    {
        return [WarmUpMessage::class];
    }

    public function warmUp(?string $cacheId = null): void
    {
        $cacheId = $cacheId ?? $this->cacheIdLoader->load();

        $criteria = new Criteria();
        $domains = $this->domainRepository->search($criteria, Context::createDefaultContext());

        $this->cacheIdLoader->write($cacheId);

        // generate all message to calculate message count
        $this->createMessages($cacheId, $domains);
    }

    public function handle($message): void
    {
        if (!$message instanceof WarmUpMessage) {
            return;
        }

        $this->callRoute($message);
    }

    private function callRoute(WarmUpMessage $message): void
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

    private function createMessages(string $cacheId, EntitySearchResult $domains): void
    {
        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            foreach ($this->registry->getWarmers() as $warmer) {
                $message = $warmer->createMessage($domain, null);

                while ($message) {
                    $offset = $message->getOffset();

                    $message->setCacheId($cacheId);
                    $message->setDomain($domain->getUrl());

                    $this->bus->dispatch($message);

                    $message = $warmer->createMessage($domain, $offset);
                }
            }
        }
    }

    private function createHttpCacheKernel(string $cacheId): HttpCache
    {
        $this->kernel->reboot(null, null, $cacheId);

        $store = $this->kernel->getContainer()->get(CacheStore::class);

        return new HttpCache($this->kernel, $store, null);
    }
}
