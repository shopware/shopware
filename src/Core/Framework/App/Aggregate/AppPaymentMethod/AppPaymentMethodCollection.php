<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppPaymentMethod;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppPaymentMethodEntity>
 */
class AppPaymentMethodCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppPaymentMethodEntity::class;
    }
}
