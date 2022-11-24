<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @package sales-channel
 *
 * @extends Collection<Sitemap>
 */
class SitemapCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Sitemap::class;
    }
}
