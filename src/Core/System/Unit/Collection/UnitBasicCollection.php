<?php declare(strict_types=1);

namespace Shopware\System\Unit\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Unit\Struct\UnitBasicStruct;

class UnitBasicCollection extends EntityCollection
{
    /**
     * @var UnitBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? UnitBasicStruct
    {
        return parent::get($id);
    }

    public function current(): UnitBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return UnitBasicStruct::class;
    }
}
