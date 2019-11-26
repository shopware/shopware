<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

interface SitemapNameGeneratorInterface
{
    public function getSitemapFilename(string $sitemapKey): string;
}
