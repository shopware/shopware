<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CatalogCollection extends EntityCollection
{
    /**
     * @var CatalogStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CatalogStruct
    {
        return parent::get($id);
    }

    public function current(): CatalogStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CatalogStruct::class;
    }
}
