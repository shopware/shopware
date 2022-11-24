<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ConfigHandler;

/**
 * @package sales-channel
 */
interface ConfigHandlerInterface
{
    public function getSitemapConfig(): array;
}
