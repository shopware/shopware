<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Provider\UrlProviderInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SitemapExporter implements SitemapExporterInterface
{
    /**
     * @var SitemapWriterInterface
     */
    private $sitemapWriter;

    /**
     * @var \IteratorAggregate
     */
    private $urlProvider;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @param \IteratorAggregate<int, UrlProviderInterface> $urlProvider
     */
    public function __construct(
        SitemapWriterInterface $sitemapWriter,
        SystemConfigService $systemConfigService,
        \IteratorAggregate $urlProvider
    ) {
        $this->sitemapWriter = $sitemapWriter;
        $this->systemConfigService = $systemConfigService;
        $this->urlProvider = $urlProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(SalesChannelContext $salesChannelContext, bool $force = false): void
    {
        if (!$this->sitemapWriter->lock($salesChannelContext)) {
            throw new AlreadyLockedException(sprintf('Cannot acquire lock for sales channel %s and language %s', $salesChannelContext->getSalesChannel()->getId(), $salesChannelContext->getSalesChannel()->getLanguageId()));
        }

        /** @var UrlProviderInterface $urlProvider */
        foreach ($this->urlProvider as $urlProvider) {
            $urlProvider->reset();

            while ($urls = $urlProvider->getUrls($salesChannelContext)) {
                $this->sitemapWriter->writeFile($salesChannelContext, $urls);
            }
        }

        $this->sitemapWriter->closeFiles();

        $this->sitemapWriter->unlock($salesChannelContext);

        $this->systemConfigService->set('core.sitemap.sitemapLastRefresh', time(), $salesChannelContext->getSalesChannel()->getId());
    }
}
