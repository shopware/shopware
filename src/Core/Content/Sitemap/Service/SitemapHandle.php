<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Exception\FileNotReadableException;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SitemapHandle implements SitemapHandleInterface
{
    private const MAX_URLS = 49999;
    private const SITEMAP_NAME_PATTERN = 'sitemap%s-%d.xml.gz';

    private array $tmpFiles = [];

    private FilesystemInterface $filesystem;

    /**
     * @var resource|false
     */
    private $handle;

    private int $index = 1;

    private int $urlCount = 0;

    private SalesChannelContext $context;

    private ?string $domainName = null;

    public function __construct(FilesystemInterface $filesystem, SalesChannelContext $context, ?string $domain = null)
    {
        $this->setDomainName($domain);

        $this->filesystem = $filesystem;
        $filePath = $this->getTmpFilePath($context);
        $this->handle = gzopen($filePath, 'ab');
        $this->printHeader();

        if ($this->handle === false) {
            throw new FileNotReadableException($filePath);
        }

        $this->tmpFiles[] = $filePath;
        $this->context = $context;
    }

    /**
     * @param Url[] $urls
     */
    public function write(array $urls): void
    {
        foreach ($urls as $url) {
            gzwrite($this->handle, (string) $url);
            ++$this->urlCount;

            if ($this->urlCount % self::MAX_URLS === 0) {
                $this->printFooter();
                gzclose($this->handle);
                ++$this->index;
                $path = $this->getTmpFilePath($this->context);
                $this->handle = gzopen($path, 'ab');
                $this->printHeader();
                $this->tmpFiles[] = $path;
            }
        }
    }

    public function finish(?bool $cleanUp = true): void
    {
        if ($cleanUp) {
            $this->cleanUp();
        }

        if (\is_resource($this->handle)) {
            $this->printFooter();
            gzclose($this->handle);
        }

        foreach ($this->tmpFiles as $i => $tmpFile) {
            $sitemapPath = $this->getFilePath($i + 1, $this->context);
            if ($this->filesystem->has($sitemapPath)) {
                $this->filesystem->delete($sitemapPath);
            }

            $this->filesystem->write($sitemapPath, file_get_contents($tmpFile));
            @unlink($tmpFile);
        }
    }

    private function getFilePath(int $index, SalesChannelContext $salesChannelContext): string
    {
        return $this->getPath($salesChannelContext) . $this->getFileName($salesChannelContext, $index);
    }

    private function getPath(SalesChannelContext $salesChannelContext): string
    {
        return 'sitemap/salesChannel-' . $salesChannelContext->getSalesChannel()->getId() . '-' . $salesChannelContext->getSalesChannel()->getLanguageId() . '/';
    }

    private function getTmpFilePath(SalesChannelContext $salesChannelContext): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/' . $this->getFileName($salesChannelContext);
    }

    private function getFileName(SalesChannelContext $salesChannelContext, ?int $index = null): string
    {
        if ($this->domainName === null) {
            return sprintf($salesChannelContext->getSalesChannel()->getId() . '-' . self::SITEMAP_NAME_PATTERN, null, $index ?? $this->index);
        }

        return sprintf($salesChannelContext->getSalesChannel()->getId() . '-' . self::SITEMAP_NAME_PATTERN, '-' . $this->domainName, $index ?? $this->index);
    }

    private function printHeader(): void
    {
        gzwrite($this->handle, '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
    }

    private function printFooter(): void
    {
        gzwrite($this->handle, '</urlset>');
    }

    private function cleanUp(): void
    {
        try {
            $files = $this->filesystem->listContents($this->getPath($this->context));
        } catch (\Throwable $e) {
            // Folder does not exists
            return;
        }

        foreach ($files as $file) {
            $this->filesystem->delete($file['path']);
        }
    }

    private function setDomainName(?string $domain = null): void
    {
        if ($domain === null) {
            return;
        }

        $host = parse_url($domain, \PHP_URL_HOST);
        if ($host) {
            $host = str_replace('.', '-', $host);
        }

        $path = parse_url($domain, \PHP_URL_PATH);
        if ($path) {
            $path = str_replace('/', '-', $path);
        }

        $this->domainName = $host . $path;
    }
}
