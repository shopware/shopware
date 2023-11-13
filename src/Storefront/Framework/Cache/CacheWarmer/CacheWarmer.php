<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Symfony\Component\Messenger\MessageBusInterface;

#[Package('storefront')]
class CacheWarmer
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly MessageBusInterface $bus,
        private readonly CacheRouteWarmerRegistry $registry,
        private readonly CacheIdLoader $cacheIdLoader
    ) {
    }

    public function warmUp(?string $cacheId = null): void
    {
        $cacheId ??= $this->cacheIdLoader->load();

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
        );
        $domains = $this->salesChannelDomainRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $this->cacheIdLoader->write($cacheId);

        // generate all message to calculate message count
        $this->createMessages($cacheId, $domains);
    }

    private function createMessages(string $cacheId, SalesChannelDomainCollection $domains): void
    {
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
