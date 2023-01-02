<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TaxEntity>
 */
#[Package('customer-order')]
class TaxCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxEntity::class;
    }
}
