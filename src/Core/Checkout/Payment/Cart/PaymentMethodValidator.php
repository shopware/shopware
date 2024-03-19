<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PaymentMethodValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $paymentMethod = $context->getPaymentMethod();
        if (!$paymentMethod->getActive()) {
            $errors->add(
                new PaymentMethodBlockedError((string) $paymentMethod->getTranslation('name'), 'inactive')
            );
        }

        $ruleId = $paymentMethod->getAvailabilityRuleId();

        $ruleIds = Feature::isActive('cache_rework') ? $cart->getRuleIds() : $context->getRuleIds();

        if ($ruleId && !\in_array($ruleId, $ruleIds, true)) {
            $errors->add(
                new PaymentMethodBlockedError((string) $paymentMethod->getTranslation('name'), 'rule not matching')
            );
        }

        if (!\in_array($paymentMethod->getId(), $context->getSalesChannel()->getPaymentMethodIds() ?? [], true)) {
            $errors->add(
                new PaymentMethodBlockedError((string) $paymentMethod->getTranslation('name'), 'not allowed')
            );
        }
    }
}
