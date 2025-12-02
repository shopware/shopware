<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Sitemap\Event\SitemapSalesChannelCriteriaEvent;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[AsMessageHandler(handles: SitemapGenerateTask::class)]
#[Package('discovery')]
final class SitemapGenerateTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $sitemapRefreshStrategy = $this->systemConfigService->getInt('core.sitemap.sitemapRefreshStrategy');
        if ($sitemapRefreshStrategy !== SitemapExporterInterface::STRATEGY_SCHEDULED_TASK) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotEqualsFilter('domains.id', null));

        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $context = Context::createCLIContext();

        $this->eventDispatcher->dispatch(
            new SitemapSalesChannelCriteriaEvent($criteria, $context)
        );

        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        foreach ($salesChannels as $salesChannel) {
            if ($salesChannel->getDomains() === null) {
                continue;
            }

            $languageIds = $salesChannel->getDomains()->map(fn (SalesChannelDomainEntity $salesChannelDomain) => $salesChannelDomain->getLanguageId());

            $languageIds = array_unique($languageIds);

            foreach ($languageIds as $languageId) {
                $this->messageBus->dispatch(new SitemapMessage($salesChannel->getId(), $languageId, null, null, false));
            }
        }
    }
}
