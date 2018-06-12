<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Framework\ORM\EntityCollection;


class UnitCollection extends EntityCollection
{
    /**
     * @var UnitStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? UnitStruct
    {
        return parent::get($id);
    }

    public function current(): UnitStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return UnitStruct::class;
    }
}
