<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Sitemap>
 */
#[Package('sales-channel')]
class SitemapCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Sitemap::class;
    }
}
