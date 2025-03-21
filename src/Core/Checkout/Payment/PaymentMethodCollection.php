<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends EntityCollection<PaymentMethodEntity>
 */
#[Package('checkout')]
class PaymentMethodCollection extends EntityCollection
{
    /**
     * @deprecated tag:v6.8.0 use RuleIdMatcher instead
     */
    public function filterByActiveRules(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0', RuleIdMatcher::class)
        );

        return $this->filter(
            function (PaymentMethodEntity $paymentMethod) use ($salesChannelContext) {
                if ($paymentMethod->getAvailabilityRuleId() === null) {
                    return true;
                }

                return \in_array($paymentMethod->getAvailabilityRuleId(), $salesChannelContext->getRuleIds(), true);
            }
        );
    }

    /**
     * @return array<string>
     */
    public function getPluginIds(): array
    {
        return $this->fmap(fn (PaymentMethodEntity $paymentMethod) => $paymentMethod->getPluginId());
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(fn (PaymentMethodEntity $paymentMethod) => $paymentMethod->getPluginId() === $id);
    }

    /**
     * Sorts the selected payment method first
     * If a different default payment method is defined, it will be sorted second
     * All other payment methods keep their respective sorting
     */
    public function sortPaymentMethodsByPreference(SalesChannelContext $context): void
    {
        $ids = array_merge(
            [$context->getSalesChannel()->getPaymentMethodId()],
            $this->getIds(),
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
