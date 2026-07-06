<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface SitemapHandleInterface
{
    /**
     * @param list<Url> $urls
     */
    public function write(array $urls): void;

    public function finish(): void;
}
