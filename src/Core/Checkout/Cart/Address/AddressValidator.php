<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address;

use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressCountryRegionMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressCountryRegionMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Service\ResetInterface;

#[Package('checkout')]
class AddressValidator implements CartValidatorInterface, ResetInterface
{
    /**
     * @var array<string, bool>
     */
    private array $available = [];

    /**
     * @internal
     *
     * @param EntityRepository<EntityCollection<Entity>> $salesChannelCountryRepository
     */
    public function __construct(private readonly EntityRepository $salesChannelCountryRepository)
    {
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $country = $context->getShippingLocation()->getCountry();
        $customer = $context->getCustomer();

        $isPhysicalLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $isPhysicalLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_PHYSICAL);

            Feature::callSilentIfInactive('v6.8.0.0', static function () use ($cart, &$isPhysicalLineItem): void {
                $isPhysicalLineItem = $isPhysicalLineItem || $cart->getLineItems()->hasLineItemWithState(State::IS_PHYSICAL);
            });
        }

        $validateShipping = $cart->getLineItems()->count() === 0 || $isPhysicalLineItem;

        if (!$country->getActive() && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name'), $context->getShippingLocation()->getAddress()?->getId()));

            return;
        }

        if (!$country->getShippingAvailable() && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name'), $context->getShippingLocation()->getAddress()?->getId()));

            return;
        }

        if (!$this->isSalesChannelCountry($country->getId(), $context) && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name'), $context->getShippingLocation()->getAddress()?->getId()));

            return;
        }

        if ($customer === null) {
            return;
        }

        $activeBillingAddress = $customer->getActiveBillingAddress();
        $activeShippingAddress = $customer->getActiveShippingAddress();

        if ($activeBillingAddress === null) {
            $errors->add(new BillingAddressMissingError());
        }

        // deliberately not gated by $validateShipping: a digital-only cart would otherwise be ordered
        // with the sales channel country as its tax basis
        if ($activeShippingAddress === null) {
            $errors->add(new ShippingAddressMissingError());
        }

        if ($activeBillingAddress === null || $activeShippingAddress === null) {
            // No need to add salutation-specific errors in this case
            return;
        }

        if (!$activeBillingAddress->getSalutationId()) {
            $errors->add(new BillingAddressSalutationMissingError($activeBillingAddress));

            return;
        }

        if (!$activeShippingAddress->getSalutationId() && $validateShipping) {
            $errors->add(new ShippingAddressSalutationMissingError($activeShippingAddress));
        }

        if ($activeBillingAddress->getCountry()?->getForceStateInRegistration()) {
            if (!$activeBillingAddress->getCountryState()) {
                $errors->add(new BillingAddressCountryRegionMissingError($activeBillingAddress));
            }
        }

        if ($activeShippingAddress->getCountry()?->getForceStateInRegistration()) {
            if (!$activeShippingAddress->getCountryState()) {
                $errors->add(new ShippingAddressCountryRegionMissingError($activeShippingAddress));
            }
        }
    }

    public function reset(): void
    {
        $this->available = [];
    }

    private function isSalesChannelCountry(string $countryId, SalesChannelContext $context): bool
    {
        if (isset($this->available[$countryId])) {
            return $this->available[$countryId];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()))
            ->addFilter(new EqualsFilter('countryId', $countryId));

        return $this->available[$countryId] = $this->salesChannelCountryRepository->searchIds($criteria, $context->getContext())->getTotal() !== 0;
    }
}
