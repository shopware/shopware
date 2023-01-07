<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @package storefront
 */
class CacheWarmer
{
    private EntityRepository $domainRepository;

    private MessageBusInterface $bus;

    private CacheRouteWarmerRegistry $registry;

    private CacheIdLoader $cacheIdLoader;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $domainRepository,
        MessageBusInterface $bus,
        CacheRouteWarmerRegistry $registry,
        CacheIdLoader $cacheIdLoader,
    ) {
        $this->domainRepository = $domainRepository;
        $this->bus = $bus;
        $this->registry = $registry;
        $this->cacheIdLoader = $cacheIdLoader;
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
}
