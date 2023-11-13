<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppPaymentMethod;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppPaymentMethodEntity>
 */
#[Package('core')]
class AppPaymentMethodCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppPaymentMethodEntity::class;
    }
}
