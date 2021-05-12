<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppPaymentMethod;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @method void                               add(AppPaymentMethodEntity $entity)
 * @method void                               set(string $key, AppPaymentMethodEntity $entity)
 * @method \Generator<AppPaymentMethodEntity> getIterator()
 * @method array<AppPaymentMethodEntity>      getElements()
 * @method AppPaymentMethodEntity|null        get(string $key)
 * @method AppPaymentMethodEntity|null        first()
 * @method AppPaymentMethodEntity|null        last()
 */
class AppPaymentMethodCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppPaymentMethodEntity::class;
    }
}
