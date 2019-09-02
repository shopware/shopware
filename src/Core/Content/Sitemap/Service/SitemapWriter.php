<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\Exception;
use League\Flysystem\FilesystemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Sitemap\Exception\UnknownFileException;
use Shopware\Core\Content\Sitemap\Struct\Sitemap;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SitemapWriter implements SitemapWriterInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var SitemapNameGeneratorInterface
     */
    private $sitemapNameGenerator;

    /**
     * @var array
     */
    private $files = [];

    /**
     * @var array<Sitemap[]>
     */
    private $sitemaps = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        SitemapNameGeneratorInterface $sitemapNameGenerator,
        FilesystemInterface $filesystem,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        SystemConfigService $systemConfigService
    ) {
        $this->sitemapNameGenerator = $sitemapNameGenerator;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param Url[] $urls
     *
     * @throws UnknownFileException
     */
    public function writeFile(SalesChannelContext $salesChannelContext, array $urls = []): bool
    {
        if (empty($urls)) {
            return false;
        }

        $fileKey = $this->getSitemapKey($salesChannelContext);

        $this->openFile($fileKey);
        foreach ($urls as $url) {
            if ($this->files[$fileKey]['urlCount'] >= self::SITEMAP_URL_LIMIT) {
                $this->closeFile($fileKey);

                $this->openFile($fileKey);
            }

            ++$this->files[$fileKey]['urlCount'];
            $this->write($this->files[$fileKey]['fileHandle'], (string) $url);
        }

        return true;
    }

    /**
     * Closes open file handles and moves sitemaps to their target location.
     *
     * @throws UnknownFileException
     */
    public function closeFiles(): void
    {
        foreach ($this->files as $filekey => $params) {
            $this->closeFile($filekey);
        }

        $this->moveFiles();
    }

    public function lock(SalesChannelContext $salesChannelContext): bool
    {
        $cacheKey = $this->generateCacheKeyForSalesChannel($salesChannelContext);
        if ($this->cache->hasItem($cacheKey)) {
            return false;
        }

        $lifeTime = (int) $this->systemConfigService->get('core.sitemap.sitemapRefreshTime');

        $lock = $this->cache->getItem($cacheKey);
        $lock->set(sprintf('Locked: %s', (new \DateTime('NOW', new \DateTimeZone('UTC')))->format(\DateTime::ATOM)))
            ->expiresAfter($lifeTime);

        return $this->cache->save($lock);
    }

    public function unlock(SalesChannelContext $salesChannelContext): bool
    {
        return $this->cache->deleteItem($this->generateCacheKeyForSalesChannel($salesChannelContext));
    }

    /**
     * @throws UnknownFileException
     */
    private function closeFile(string $filekey): bool
    {
        if (!array_key_exists($filekey, $this->files)) {
            throw new UnknownFileException(sprintf('No open file "%s"', $filekey));
        }

        $fileHandle = $this->files[$filekey]['fileHandle'];
        $this->write($fileHandle, '</urlset>');

        gzclose($fileHandle);

        if (!array_key_exists($filekey, $this->sitemaps)) {
            $this->sitemaps[$filekey] = [];
        }

        $this->sitemaps[$filekey][] = new Sitemap(
            $this->files[$filekey]['fileName'],
            $this->files[$filekey]['urlCount']
        );

        unset($this->files[$filekey]);

        return true;
    }

    private function openFile(string $fileKey): bool
    {
        if (array_key_exists($fileKey, $this->files)) {
            return true;
        }

        $filePath = sprintf(
            '%s/sitemap-salesChannel-%s-%d.xml.gz',
            rtrim(sys_get_temp_dir(), '/'),
            $fileKey,
            microtime(true) * 10000
        );

        $fileHandler = gzopen($filePath, 'wb');

        if (!$fileHandler) {
            $this->logger->error(sprintf('Could not generate sitemap file, unable to write to "%s"', $filePath));

            return false;
        }

        $this->files[$fileKey] = [
            'fileHandle' => $fileHandler,
            'fileName' => $filePath,
            'urlCount' => 0,
        ];

        $this->write($fileHandler, '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        return true;
    }

    /**
     * @param resource $fileHandler
     */
    private function write($fileHandler, string $content): void
    {
        gzwrite($fileHandler, $content);
    }

    /**
     * Makes sure all files get closed and replaces the old sitemaps with the freshly generated ones
     */
    private function moveFiles(): void
    {
        /** @var Sitemap[] $sitemaps */
        foreach ($this->sitemaps as $filekey => $sitemaps) {
            // Delete old sitemaps for this siteId
            foreach ($this->filesystem->listContents('sitemap/salesChannel-' . $filekey) as $file) {
                $this->filesystem->delete($file['path']);
            }

            // Move new sitemaps into place
            foreach ($sitemaps as $sitemap) {
                $sitemapFileName = $this->sitemapNameGenerator->getSitemapFilename($filekey);
                try {
                    $this->filesystem->write($sitemapFileName, file_get_contents($sitemap->getFilename()));
                } catch (Exception $exception) {
                    // If we could not move the file to it's target, we remove it here to not clutter tmp dir
                    unlink($sitemap->getFilename());

                    $this->logger->error(sprintf('Could not move sitemap to "%s" in the location for sitemaps', $sitemapFileName));
                }
            }
        }
    }

    private function generateCacheKeyForSalesChannel(SalesChannelContext $salesChannelContext): string
    {
        return sprintf('sitemap-exporter-running-%s-%s', $salesChannelContext->getSalesChannel()->getId(), $salesChannelContext->getSalesChannel()->getLanguageId());
    }

    private function getSitemapKey(SalesChannelContext $salesChannelContext): string
    {
        return $salesChannelContext->getSalesChannel()->getId() . '-' . $salesChannelContext->getSalesChannel()->getLanguageId();
    }
}
