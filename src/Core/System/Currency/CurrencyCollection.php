<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CurrencyEntity>
 */
#[Package('core')]
class CurrencyCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'currency_collection';
    }

    protected function getExpectedClass(): string
    {
        return CurrencyEntity::class;
    }
}
