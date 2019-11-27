<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ConfigHandler;

interface ConfigHandlerInterface
{
    public function getSitemapConfig(): array;
}
