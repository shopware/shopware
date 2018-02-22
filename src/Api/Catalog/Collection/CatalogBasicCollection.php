<?php declare(strict_types=1);

namespace Shopware\Api\Catalog\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Catalog\Struct\CatalogBasicStruct;

class CatalogBasicCollection extends EntityCollection
{
    /**
     * @var CatalogBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CatalogBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CatalogBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CatalogBasicStruct::class;
    }
}
