<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address;

use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ProfileSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AddressValidator implements CartValidatorInterface
{
    private EntityRepositoryInterface $repository;

    /**
     * @var array<string, bool>
     */
    private array $available = [];

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $country = $context->getShippingLocation()->getCountry();
        $customer = $context->getCustomer();

        if (!$country->getActive()) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$country->getShippingAvailable()) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$this->isSalesChannelCountry($country->getId(), $context)) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if ($customer === null) {
            return;
        }

        if (!$this->isValidSalutationId($customer->getSalutationId())) {
            $errors->add(new ProfileSalutationMissingError($customer));

            return;
        }

        if ($customer->getActiveBillingAddress() === null || $customer->getActiveShippingAddress() === null) {
            // No need to add salutation-specific errors in this case
            return;
        }

        if (!$this->isValidSalutationId($customer->getActiveBillingAddress()->getSalutationId())) {
            $errors->add(new BillingAddressSalutationMissingError($customer->getActiveBillingAddress()));

            return;
        }

        if (!$this->isValidSalutationId($customer->getActiveShippingAddress()->getSalutationId())) {
            $errors->add(new ShippingAddressSalutationMissingError($customer->getActiveShippingAddress()));
        }
    }

    private function isSalesChannelCountry(string $countryId, SalesChannelContext $context): bool
    {
        if (isset($this->available[$countryId])) {
            return $this->available[$countryId];
        }

        $criteria = new Criteria([$countryId]);
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));

        $salesChannelCountryIds = $this->repository->searchIds($criteria, $context->getContext());

        return $this->available[$countryId] = $salesChannelCountryIds->has($countryId);
    }

    private function isValidSalutationId(?string $salutationId = null): bool
    {
        return $salutationId !== null && $salutationId !== Defaults::SALUTATION;
    }
}
