<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;

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

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        EntityRepositoryInterface $salesChannelRepository,
        SalesChannelContextFactory $salesChannelContextFactory,
        SitemapExporterInterface $sitemapExporter,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        MessageBusInterface $messageBus
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->salesChannelRepository = $salesChannelRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->sitemapExporter = $sitemapExporter;
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->messageBus = $messageBus;
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
        // starts the generation of the sitemap
        $this->messageBus->dispatch(new SitemapMessage(null, null, null, null, false));
    }

    /**
     * @param SitemapGenerateTask|SitemapMessage $message
     */
    public function handle($message): void
    {
        if ((int) $this->systemConfigService->get('core.sitemap.sitemapRefreshStrategy') !== SitemapExporterInterface::STRATEGY_SCHEDULED_TASK) {
            return;
        }

        if ($message instanceof SitemapMessage) {
            // generate sitemap via message queue
            $this->generate($message);
        } else {
            // initial call
            // call parent which handles the scheduled task logic and executes the run method which triggerst the sitemap generation
            parent::handle($message);
        }
    }

    private function generate(SitemapMessage $message): void
    {
        $salesChannelContext = $this->getSalesChannelContext($message);

        if (!($salesChannelContext instanceof SalesChannelContext)) {
            $this->logger->debug('no sale schannel context found');

            return;
        }

        try {
            $result = $this->sitemapExporter->generate($salesChannelContext, true, $message->getLastProvider(), $message->getNextOffset());

            $newMessage = new SitemapMessage($result->getLastSalesChannelId(), $result->getLastLanguageId(), $result->getProvider(), $result->getOffset(), $result->isFinish());

            $this->messageBus->dispatch($newMessage);
        } catch (AlreadyLockedException $exception) {
            $this->logger->error(sprintf('ERROR: %s', $exception->getMessage()));
        }
    }

    private function getSalesChannelContext(SitemapMessage $message)
    {
        if ($message->isFinished() === false && $message->getLastSalesChannelId() !== null) {
            $this->logger->debug('continue with last used saleschannel ' . $message->getLastSalesChannelId() . ' ' . $message->getLastLanguageId());

            // continue with last used sales channel
            return $this->salesChannelContextFactory->create('', $message->getLastSalesChannelId(), [SalesChannelContextService::LANGUAGE_ID => $message->getLastLanguageId()]);
        }

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAssociation('domains');

        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        if ($message->getLastSalesChannelId() === null) {
            // task hasn't been executed, use first saleschannel and its first language
            $salesChannel = $salesChannels->first();

            return $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $salesChannel->getLanguageId()]);
        }

        // generation for last sales channel and last language has been finished
        // case1: task needs to continue with next language.
        // case2: if there isn't a next language then use first language of next sales channel
        // case3: if there isn't a next sales channel the task is finished
        $useNextChannel = false;
        $useNextLanguage = false;
        foreach ($salesChannels as $salesChannel) {
            if ($useNextChannel === true || $useNextLanguage === true) {
                // case1 or case2
                return $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $salesChannel->getLanguageId()]);
            }

            if ($salesChannel->getId() !== $message->getLastSalesChannelId()) {
                continue;
            }

            if (\count($salesChannel->getDomains()) === 0) {
                // last language is only language of sales channel
                $useNextChannel = true;

                continue;
            }

            foreach ($salesChannel->getDomains() as $domain) {
                if ($useNextLanguage === true) {
                    return $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $domain->getLanguageId()]);
                }

                if ($domain->getLanguageId() !== $message->getLastLanguageId()) {
                    continue;
                }

                $useNextLanguage = true;
            }
        }

        return null;
    }
}
