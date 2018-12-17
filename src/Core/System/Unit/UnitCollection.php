<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class UnitCollection extends EntityCollection
{
    /**
     * @var UnitEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? UnitEntity
    {
        return parent::get($id);
    }

    public function current(): UnitEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return UnitEntity::class;
    }
}
