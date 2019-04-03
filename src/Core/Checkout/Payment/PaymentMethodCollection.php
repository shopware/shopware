<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @method void                     add(PaymentMethodEntity $entity)
 * @method void                     set(string $key, PaymentMethodEntity $entity)
 * @method PaymentMethodEntity[]    getIterator()
 * @method PaymentMethodEntity[]    getElements()
 * @method PaymentMethodEntity|null get(string $key)
 * @method PaymentMethodEntity|null first()
 * @method PaymentMethodEntity|null last()
 */
class PaymentMethodCollection extends EntityCollection
{
    public function filterByActiveRules(SalesChannelContext $checkoutContext): PaymentMethodCollection
    {
        return $this->filter(
            function (PaymentMethodEntity $paymentMethod) use ($checkoutContext) {
                if ($paymentMethod->getAvailabilityRuleIds() === null) {
                    return true;
                }

                $matches = array_intersect($paymentMethod->getAvailabilityRuleIds(), $checkoutContext->getRuleIds());

                return !empty($matches);
            }
        );
    }

    public function getPluginIds(): array
    {
        return $this->fmap(function (PaymentMethodEntity $paymentMethod) {
            return $paymentMethod->getPluginId();
        });
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(function (PaymentMethodEntity $paymentMethod) use ($id) {
            return $paymentMethod->getPluginId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodEntity::class;
    }
}
