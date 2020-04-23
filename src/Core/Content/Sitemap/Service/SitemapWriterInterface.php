<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/** @deprecated tag:v6.3.0 */
interface SitemapWriterInterface
{
    /**
     * @return resource
     */
    public function createFile(string $fileName);

    /**
     * @return resource
     */
    public function openFile(string $fileName);

    /**
     * @param Url[]    $urls
     * @param resource $fileHandle
     */
    public function writeUrlsToFile(array $urls, $fileHandle): void;

    /**
     * @param resource $fileHandle
     */
    public function closeFile($fileHandle): void;

    /**
     * @param resource $fileHandle
     */
    public function finishFile($fileHandle): void;

    public function moveFile(string $fileName, SalesChannelContext $salesChannelContext): void;
}
