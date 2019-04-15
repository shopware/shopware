<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaymentMethodValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        if (!$context->getPaymentMethod()->getAvailabilityRuleId()
            || in_array($context->getPaymentMethod()->getAvailabilityRuleId(), $context->getRuleIds(), true)) {
            return;
        }

        $errors->add(new PaymentMethodBlockedError(
            $context->getPaymentMethod()->getName() ?: $context->getPaymentMethod()->getId()
        ));
    }
}
