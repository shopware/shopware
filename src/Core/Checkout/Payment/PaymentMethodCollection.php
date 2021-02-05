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

                return \in_array($paymentMethod->getAvailabilityRuleId(), $salesChannelContext->getRuleIds(), true);
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

    /**
     * Sorts the selected payment method first
     * If a different default payment method is defined, it will be sorted second
     * All other payment methods keep their respective sorting
     */
    public function sortPaymentMethodsByPreference(SalesChannelContext $context): void
    {
        $ids = array_merge(
            [$context->getPaymentMethod()->getId(), $context->getSalesChannel()->getPaymentMethodId()],
            $this->getIds()
        );

        $this->sortByIdArray($ids);
    }

    public function getApiAlias(): string
    {
        return 'payment_method_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodEntity::class;
    }
}
