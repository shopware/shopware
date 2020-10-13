<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Provider\UrlProviderInterface;
use Shopware\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use function sprintf;

class SitemapExporter implements SitemapExporterInterface
{
    /**
     * @var UrlProviderInterface[]
     */
    private $urlProvider;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlPlaceholderHandler;

    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var SitemapHandleFactoryInterface
     */
    private $sitemapHandleFactory;

    public function __construct(
        iterable $urlProvider,
        CacheItemPoolInterface $cache,
        int $batchSize,
        SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        FilesystemInterface $filesystem,
        SitemapHandleFactoryInterface $sitemapHandleFactory
    ) {
        $this->urlProvider = $urlProvider;
        $this->cache = $cache;
        $this->batchSize = $batchSize;
        $this->seoUrlPlaceholderHandler = $seoUrlPlaceholderHandler;
        $this->filesystem = $filesystem;
        $this->sitemapHandleFactory = $sitemapHandleFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(SalesChannelContext $salesChannelContext, bool $force = false, ?string $lastProvider = null, ?int $offset = null): SitemapGenerationResult
    {
        $this->lock($salesChannelContext, $force);

        try {
            $host = $this->getHost($salesChannelContext);

            $sitemapHandle = $this->sitemapHandleFactory->create($this->filesystem, $salesChannelContext);

            foreach ($this->urlProvider as $urlProvider) {
                do {
                    $result = $urlProvider->getUrls($salesChannelContext, $this->batchSize, $offset);

                    foreach ($result->getUrls() as $url) {
                        $url->setLoc($this->seoUrlPlaceholderHandler->replace($url->getLoc(), $host, $salesChannelContext));
                    }

                    $sitemapHandle->write($result->getUrls());
                    $needRun = $result->getNextOffset() !== null;
                    $offset = $result->getNextOffset();
                } while ($needRun);
            }

            $sitemapHandle->finish();
        } finally {
            $this->unlock($salesChannelContext);
        }

        return new SitemapGenerationResult(
            true,
            $lastProvider,
            null,
            $salesChannelContext->getSalesChannel()->getId(),
            $salesChannelContext->getSalesChannel()->getLanguageId()
        );
    }

    private function lock(SalesChannelContext $salesChannelContext, bool $force): void
    {
        $key = $this->generateCacheKeyForSalesChannel($salesChannelContext);
        $item = $this->cache->getItem($key);
        if ($item->isHit() && !$force) {
            throw new AlreadyLockedException($salesChannelContext);
        }

        $item->set(true);
        $this->cache->save($item);
    }

    private function unlock(SalesChannelContext $salesChannelContext): void
    {
        $this->cache->deleteItem($this->generateCacheKeyForSalesChannel($salesChannelContext));
    }

    private function generateCacheKeyForSalesChannel(SalesChannelContext $salesChannelContext): string
    {
        return sprintf('sitemap-exporter-running-%s-%s', $salesChannelContext->getSalesChannel()->getId(), $salesChannelContext->getSalesChannel()->getLanguageId());
    }

    private function getHost(SalesChannelContext $salesChannelContext): string
    {
        $domains = $salesChannelContext->getSalesChannel()->getDomains();
        $languageId = $salesChannelContext->getSalesChannel()->getLanguageId();

        if ($domains instanceof SalesChannelDomainCollection) {
            foreach ($domains as $domain) {
                if ($domain->getLanguageId() === $languageId) {
                    return $domain->getUrl();
                }
            }
        }

        return '';
    }
}
