<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void         add(Sitemap $entity)
 * @method void         set(string $key, Sitemap $entity)
 * @method Sitemap[]    getIterator()
 * @method Sitemap[]    getElements()
 * @method Sitemap|null get(string $key)
 * @method Sitemap|null first()
 * @method Sitemap|null last()
 */
class SitemapCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Sitemap::class;
    }
}
