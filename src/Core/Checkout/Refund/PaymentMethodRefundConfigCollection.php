<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                 add(PaymentMethodRefundConfigEntity $entity)
 * @method void                                 set(string $key, PaymentMethodRefundConfigEntity $entity)
 * @method PaymentMethodRefundConfigEntity[]    getIterator()
 * @method PaymentMethodRefundConfigEntity[]    getElements()
 * @method PaymentMethodRefundConfigEntity|null get(string $key)
 * @method PaymentMethodRefundConfigEntity|null first()
 * @method PaymentMethodRefundConfigEntity|null last()
 */
class PaymentMethodRefundConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_method_refund_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodRefundConfigEntity::class;
    }
}
