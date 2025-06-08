<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ConfigHandler;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface ConfigHandlerInterface
{
    /**
     * @return array<string, array<array<string, mixed>>>
     */
    public function getSitemapConfig(): array;
}
