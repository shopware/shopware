<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Sitemap\Event\SitemapSalesChannelCriteriaEvent;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SitemapGenerateTaskHandler extends ScheduledTaskHandler
{
    private EntityRepositoryInterface $salesChannelRepository;

    private AbstractSalesChannelContextFactory $salesChannelContextFactory;

    private SitemapExporterInterface $sitemapExporter;

    private LoggerInterface $logger;

    private SystemConfigService $systemConfigService;

    private MessageBusInterface $messageBus;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        EntityRepositoryInterface $salesChannelRepository,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        SitemapExporterInterface $sitemapExporter,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        MessageBusInterface $messageBus,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->salesChannelRepository = $salesChannelRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->sitemapExporter = $sitemapExporter;
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->messageBus = $messageBus;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            SitemapGenerateTask::class,
            SitemapMessage::class,
        ];
    }

    public function run(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('domains.id', null)]
        ));

        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $context = Context::createDefaultContext();

        $this->eventDispatcher->dispatch(
            new SitemapSalesChannelCriteriaEvent($criteria, $context)
        );

        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            if ($salesChannel->getDomains() === null) {
                continue;
            }

            $languageIds = $salesChannel->getDomains()->map(function (SalesChannelDomainEntity $salesChannelDomain) {
                return $salesChannelDomain->getLanguageId();
            });

            $languageIds = array_unique($languageIds);

            foreach ($languageIds as $languageId) {
                $this->messageBus->dispatch(new SitemapMessage($salesChannel->getId(), $languageId, null, null, false));
            }
        }
    }

    /**
     * @param SitemapGenerateTask|SitemapMessage $message
     *
     * @throws \Throwable
     */
    public function handle($message): void
    {
        $sitemapRefreshStrategy = $this->systemConfigService->getInt('core.sitemap.sitemapRefreshStrategy');
        if ($sitemapRefreshStrategy !== SitemapExporterInterface::STRATEGY_SCHEDULED_TASK) {
            return;
        }

        if ($message instanceof SitemapMessage) {
            $this->generate($message);

            return;
        }

        if ($message instanceof SitemapGenerateTask) {
            parent::handle($message);

            return;
        }
    }

    private function generate(SitemapMessage $message): void
    {
        if ($message->getLastSalesChannelId() === null || $message->getLastLanguageId() === null) {
            return;
        }

        $context = $this->salesChannelContextFactory->create('', $message->getLastSalesChannelId(), [SalesChannelContextService::LANGUAGE_ID => $message->getLastLanguageId()]);

        try {
            $this->sitemapExporter->generate($context, true, $message->getLastProvider(), $message->getNextOffset());
        } catch (AlreadyLockedException $exception) {
            $this->logger->error(sprintf('ERROR: %s', $exception->getMessage()));
        }
    }
}
