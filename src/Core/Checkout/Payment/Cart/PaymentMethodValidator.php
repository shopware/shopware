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
        if (!$context->getPaymentMethod()->getActive()) {
            $errors->add(
                new PaymentMethodBlockedError((string) $context->getPaymentMethod()->getTranslation('name'))
            );
        }

        $ruleId = $context->getPaymentMethod()->getAvailabilityRuleId();

        if ($ruleId && !\in_array($ruleId, $context->getRuleIds(), true)) {
            $errors->add(
                new PaymentMethodBlockedError((string) $context->getPaymentMethod()->getTranslation('name'))
            );
        }

        if (!\in_array($context->getPaymentMethod()->getId(), $context->getSalesChannel()->getPaymentMethodIds() ?? [], true)) {
            $errors->add(
                new PaymentMethodBlockedError((string) $context->getPaymentMethod()->getTranslation('name'))
            );
        }
    }
}
