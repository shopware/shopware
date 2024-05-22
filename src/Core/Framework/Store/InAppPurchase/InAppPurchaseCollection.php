<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<InAppPurchaseEntity>
 */
#[Package('core')]
class InAppPurchaseCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return InAppPurchaseEntity::class;
    }
}
