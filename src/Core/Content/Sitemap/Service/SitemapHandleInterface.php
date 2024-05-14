<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
interface SitemapHandleInterface
{
    public function write(array $urls): void;

    public function finish(): void;
}
