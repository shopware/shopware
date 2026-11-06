<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address;

use Shopware\Core\Checkout\Cart\Address\Error\VatIdMissingError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class VatIdValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();

        if ($customer === null || $customer->getAccountType() !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return;
        }

        if (!$customer->getActiveBillingAddress()?->getCountry()?->getVatIdRequired()) {
            return;
        }

        if (\array_filter($customer->getVatIds() ?? []) !== []) {
            return;
        }

        $errors->add(new VatIdMissingError());
    }
}
