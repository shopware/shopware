<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CatalogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CatalogEntity::class;
    }
}
