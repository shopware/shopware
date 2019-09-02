<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SitemapExporterInterface
{
    public const STRATEGY_MANUAL = 1;
    public const STRATEGY_SCHEDULED_TASK = 2;
    public const STRATEGY_LIVE = 3;

    public function generate(SalesChannelContext $salesChannelContext, bool $force = false): void;
}
