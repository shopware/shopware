<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CurrencyCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CurrencyEntity::class;
    }
}
