<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

/** @deprecated tag:v6.3.0 */
interface SitemapNameGeneratorInterface
{
    public function getSitemapFilename(string $sitemapKey): string;
}
