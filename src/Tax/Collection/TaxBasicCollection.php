<?php declare(strict_types=1);

namespace Shopware\Tax\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Tax\Struct\TaxBasicStruct;

class TaxBasicCollection extends EntityCollection
{
    /**
     * @var TaxBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? TaxBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): TaxBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return TaxBasicStruct::class;
    }
}
