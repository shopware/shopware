<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Unit\UnitBasicStruct;

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
