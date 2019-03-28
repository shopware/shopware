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
    public function filterByActiveRules(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        return $this->filter(
            function (PaymentMethodEntity $paymentMethod) use ($salesChannelContext) {
                if ($paymentMethod->getAvailabilityRuleId() === null) {
                    return true;
                }

                return in_array($paymentMethod->getAvailabilityRuleId(), $salesChannelContext->getRuleIds(), true);
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
