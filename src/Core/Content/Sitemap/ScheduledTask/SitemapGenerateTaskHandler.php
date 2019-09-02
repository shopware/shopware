<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SitemapGenerateTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    /**
     * @var SitemapExporterInterface
     */
    private $sitemapExporter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        EntityRepositoryInterface $salesChannelRepository,
        SalesChannelContextFactory $salesChannelContextFactory,
        SitemapExporterInterface $sitemapExporter,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->salesChannelRepository = $salesChannelRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->sitemapExporter = $sitemapExporter;
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
    }

    public static function getHandledMessages(): iterable
    {
        return [SitemapGenerateTask::class];
    }

    public function run(): void
    {
        if ((int) $this->systemConfigService->get('core.sitemap.sitemapRefreshStrategy') !== SitemapExporterInterface::STRATEGY_SCHEDULED_TASK) {
            return;
        }

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAssociation('domains');

        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            if (\count($salesChannel->getDomains()) === 0) {
                $languageIds = [$salesChannel->getLanguageId()];
            } else {
                $languageIds = $salesChannel->getDomains()->map(function (SalesChannelDomainEntity $salesChannelDomain) {
                    return $salesChannelDomain->getLanguageId();
                });
            }

            foreach ($languageIds as $languageId) {
                $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $languageId]);

                try {
                    $this->sitemapExporter->generate($salesChannelContext);
                } catch (AlreadyLockedException $exception) {
                    $this->logger->error(sprintf('ERROR: %s', $exception->getMessage()));
                }
            }
        }
    }
}
