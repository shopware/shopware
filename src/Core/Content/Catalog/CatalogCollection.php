<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CatalogCollection extends EntityCollection
{
    /**
     * @var CatalogEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? CatalogEntity
    {
        return parent::get($id);
    }

    public function current(): CatalogEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CatalogEntity::class;
    }
}
