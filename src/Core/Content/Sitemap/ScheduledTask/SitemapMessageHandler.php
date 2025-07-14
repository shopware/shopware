<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('discovery')]
final readonly class SitemapMessageHandler
{
    /**
     * @internal
     */
    public function __construct(
        private AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private SitemapExporterInterface $sitemapExporter,
        private LoggerInterface $logger,
        private SystemConfigService $systemConfigService,
    ) {
    }

    public function __invoke(SitemapMessage $message): void
    {
        $sitemapRefreshStrategy = $this->systemConfigService->getInt('core.sitemap.sitemapRefreshStrategy');
        if ($sitemapRefreshStrategy !== SitemapExporterInterface::STRATEGY_SCHEDULED_TASK) {
            return;
        }

        $this->generate($message);
    }

    private function generate(SitemapMessage $message): void
    {
        if ($message->getLastSalesChannelId() === null || $message->getLastLanguageId() === null) {
            return;
        }

        $salesChannelContext = $this->salesChannelContextFactory->create('', $message->getLastSalesChannelId(), [SalesChannelContextService::LANGUAGE_ID => $message->getLastLanguageId()]);

        try {
            $this->sitemapExporter->generate($salesChannelContext, true, $message->getLastProvider(), $message->getNextOffset());
        } catch (AlreadyLockedException $exception) {
            $this->logger->error(\sprintf('ERROR: %s', $exception->getMessage()));
        }
    }
}
