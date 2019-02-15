<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class NumberRangeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NumberRangeEntity::class;
    }
}
