<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Exception\FileNotReadableException;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/** @deprecated tag:v6.3.0 */
class SitemapWriter implements SitemapWriterInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function createFile(string $fileName)
    {
        $filePath = $this->getTmpFilePath($fileName);

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $fileHandle = $this->openFile($fileName);

        gzwrite($fileHandle, '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        return $fileHandle;
    }

    public function openFile(string $fileName)
    {
        $filePath = $this->getTmpFilePath($fileName);

        $fileHandle = gzopen($filePath, 'ab');

        if ($fileHandle === false) {
            throw new FileNotReadableException($filePath);
        }

        return $fileHandle;
    }

    /**
     * @param Url[]    $urls
     * @param resource $fileHandle
     */
    public function writeUrlsToFile(array $urls, $fileHandle): void
    {
        foreach ($urls as $url) {
            gzwrite($fileHandle, (string) $url);
        }
    }

    public function closeFile($fileHandle): void
    {
        gzclose($fileHandle);
    }

    public function finishFile($fileHandle): void
    {
        gzwrite($fileHandle, '</urlset>');
        $this->closeFile($fileHandle);
    }

    public function moveFile(string $fileName, SalesChannelContext $salesChannelContext): void
    {
        $sitemapPath = $this->getPath($fileName, $salesChannelContext);

        if ($this->filesystem->has($sitemapPath)) {
            $this->filesystem->delete($sitemapPath);
        }

        $this->filesystem->write($sitemapPath, file_get_contents($this->getTmpFilePath($fileName)));
    }

    private function getTmpFilePath(string $fileName): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/' . $fileName;
    }

    private function getPath(string $fileName, SalesChannelContext $salesChannelContext): string
    {
        return 'sitemap/salesChannel-' . $this->getSitemapKey($salesChannelContext) . '/' . $fileName;
    }

    private function getSitemapKey(SalesChannelContext $salesChannelContext): string
    {
        return $salesChannelContext->getSalesChannel()->getId() . '-' . $salesChannelContext->getSalesChannel()->getLanguageId();
    }
}
