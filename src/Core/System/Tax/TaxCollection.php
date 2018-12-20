<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class TaxCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TaxEntity::class;
    }
}
