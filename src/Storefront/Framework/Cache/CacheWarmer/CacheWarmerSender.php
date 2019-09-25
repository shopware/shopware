<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Messenger\MessageBusInterface;

class CacheWarmerSender
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

    public function __construct(
        EntityRepositoryInterface $domainRepository,
        MessageBusInterface $bus,
        CacheRouteWarmerRegistry $registry
    ) {
        $this->domainRepository = $domainRepository;
        $this->bus = $bus;
        $this->registry = $registry;
    }

    public function send(): void
    {
        $criteria = new Criteria();
        $domains = $this->domainRepository->search($criteria, Context::createDefaultContext());

        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            foreach ($this->registry->getWarmers() as $warmer) {
                $this->bus->dispatch(new IteratorMessage($domain, get_class($warmer)));
            }
        }
    }
}
