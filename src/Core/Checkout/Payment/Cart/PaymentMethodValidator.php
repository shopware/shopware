<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PaymentMethodValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $paymentMethod = $context->getPaymentMethod();
        $transactionIndex = $this->findTransactionIndex($cart, $paymentMethod->getId());

        if (!$paymentMethod->getActive()) {
            $errors->add(
                new PaymentMethodBlockedError(
                    id: $paymentMethod->getId(),
                    name: (string) $paymentMethod->getTranslation('name'),
                    reason: 'inactive',
                    transactionIndex: $transactionIndex,
                )
            );
        }

        $ruleId = $paymentMethod->getAvailabilityRuleId();

        if ($ruleId && !\in_array($ruleId, $context->getRuleIds(), true)) {
            $errors->add(
                new PaymentMethodBlockedError(
                    id: $paymentMethod->getId(),
                    name: (string) $paymentMethod->getTranslation('name'),
                    reason: 'rule not matching',
                    transactionIndex: $transactionIndex,
                )
            );
        }

        if (!\in_array($paymentMethod->getId(), $context->getSalesChannel()->getPaymentMethodIds() ?? [], true)) {
            $errors->add(
                new PaymentMethodBlockedError(
                    id: $paymentMethod->getId(),
                    name: (string) $paymentMethod->getTranslation('name'),
                    reason: 'not allowed',
                    transactionIndex: $transactionIndex,
                )
            );
        }
    }

    private function findTransactionIndex(Cart $cart, string $paymentMethodId): ?int
    {
        foreach (array_values($cart->getTransactions()->getElements()) as $transactionIndex => $transaction) {
            if ($transaction->getPaymentMethodId() === $paymentMethodId) {
                return $transactionIndex;
            }
        }

        return null;
    }
}
