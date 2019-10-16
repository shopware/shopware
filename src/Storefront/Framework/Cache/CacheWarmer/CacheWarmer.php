<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Doctrine\DBAL\Connection;
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
     * @var Connection
     */
    private $connection;

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

    public function __construct(
        EntityRepositoryInterface $domainRepository,
        MessageBusInterface $bus,
        CacheRouteWarmerRegistry $registry,
        Connection $connection,
        Kernel $kernel,
        RouterInterface $router,
        RequestTransformerInterface $requestTransformer
    ) {
        $this->domainRepository = $domainRepository;
        $this->bus = $bus;
        $this->registry = $registry;
        $this->connection = $connection;
        $this->kernel = $kernel;
        $this->router = $router;
        $this->requestTransformer = $requestTransformer;
    }

    public static function getHandledMessages(): iterable
    {
        return [WarmUpMessage::class];
    }

    public function warmUp(string $cacheId): void
    {
        $criteria = new Criteria();
        $domains = $this->domainRepository->search($criteria, Context::createDefaultContext());

        // generate all message to calculate message count
        $messages = $this->createMessages($cacheId, $domains);

        // write message count to storage for ready validation
        $this->connection->executeUpdate(
            'REPLACE INTO app_config (`key`, `value`) VALUES (:key, :value)',
            ['key' => $this->getWarmUpKey($cacheId), 'value' => count($messages)]
        );

        // send messages to queue (messages handled in this class too)
        foreach ($messages as $message) {
            $this->bus->dispatch($message);
        }
    }

    public function handle($message): void
    {
        if (!$message instanceof WarmUpMessage) {
            return;
        }

        $cacheId = $message->getCacheId();

        // reduce count of messages to handle
        $this->reduceQueueCount($cacheId);

        $this->callRoute($message);

        // check if warm up is ready and switch cache
        if ($this->isReady($cacheId)) {
            $this->switchCache($cacheId);
        }
    }

    private function callRoute(WarmUpMessage $message): void
    {
        $kernel = $this->createHttpCacheKernel($message->getCacheId());

        foreach ($message->getParameters() as $parameters) {
            $url = rtrim($message->getDomain(), '/') . $this->router->generate($message->getRoute(), $parameters);

            $request = $this->requestTransformer->transform(Request::create($url));

            $kernel->handle($request);
        }
    }

    private function createMessages(string $cacheId, EntitySearchResult $domains): array
    {
        $messages = [];

        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            foreach ($this->registry->getWarmers() as $warmer) {
                $message = $warmer->createMessage($domain, null);

                while ($message) {
                    $offset = $message->getOffset();

                    $message->setCacheId($cacheId);
                    $message->setDomain($domain->getUrl());

                    $messages[] = $message;

                    $message = $warmer->createMessage($domain, $offset);
                }
            }
        }

        return $messages;
    }

    private function createHttpCacheKernel(string $cacheId): HttpCache
    {
        $this->kernel->reboot(null, null, $cacheId);

        $store = $this->kernel->getContainer()->get(CacheStore::class);

        return new HttpCache($this->kernel, $store, null);
    }

    private function reduceQueueCount(string $cacheId): void
    {
        $this->connection->executeUpdate(
            'UPDATE app_config SET `value` = `value` - 1 WHERE `key` = :key',
            ['key' => $this->getWarmUpKey($cacheId)]
        );
    }

    private function switchCache(string $cacheId): void
    {
        $this->connection->executeUpdate(
            'UPDATE app_config SET `value` = :cacheId WHERE `key` = :key',
            ['cacheId' => $cacheId, 'key' => 'cache-id']
        );

        $this->connection->executeUpdate(
            'DELETE FROM app_config WHERE `key` = :key',
            ['key' => $this->getWarmUpKey($cacheId)]
        );
    }

    private function isReady(string $cacheId): bool
    {
        $count = $this->connection->fetchColumn(
            'SELECT `value` FROM app_config WHERE `key` = :key',
            ['key' => $this->getWarmUpKey($cacheId)]
        );

        return (int) $count <= 0;
    }

    private function getWarmUpKey(string $cacheId): string
    {
        return 'warm-up-' . $cacheId;
    }
}
