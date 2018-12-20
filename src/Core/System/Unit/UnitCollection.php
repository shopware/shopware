<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class UnitCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return UnitEntity::class;
    }
}
