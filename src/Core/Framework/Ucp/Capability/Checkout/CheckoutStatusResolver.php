<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Translates a Shopware {@see Cart} state into the UCP checkout `status`
 * enumeration. The lookup is intentionally conservative — when in doubt,
 * we mark the checkout as `incomplete`, never as `ready_for_complete`,
 * so platforms always re-verify before completion.
 *
 * @internal
 */
#[Package('framework')]
class CheckoutStatusResolver
{
    public function resolve(Cart $cart, SalesChannelContext $context, bool $orderJustPlaced = false): string
    {
        if ($orderJustPlaced) {
            return CheckoutStatus::COMPLETE_IN_PROGRESS;
        }

        $errors = $cart->getErrors();

        foreach ($errors as $error) {
            if ($this->isUnrecoverable($error)) {
                return CheckoutStatus::REQUIRES_ESCALATION;
            }
        }

        foreach ($errors as $error) {
            if ($this->requiresBuyerInput($error)) {
                return CheckoutStatus::REQUIRES_ESCALATION;
            }
        }

        if ($cart->getLineItems()->count() === 0) {
            return CheckoutStatus::INCOMPLETE;
        }

        if (!$this->hasShippingAddress($context)) {
            return CheckoutStatus::INCOMPLETE;
        }

        if (!$this->hasPaymentMethod($context)) {
            return CheckoutStatus::INCOMPLETE;
        }

        $persistentErrors = false;
        foreach ($errors as $error) {
            if ($error->isPersistent()) {
                $persistentErrors = true;
                break;
            }
        }

        if ($persistentErrors) {
            return CheckoutStatus::INCOMPLETE;
        }

        return CheckoutStatus::READY_FOR_COMPLETE;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildMessages(Cart $cart): array
    {
        $messages = [];
        foreach ($cart->getErrors() as $error) {
            $messages[] = [
                'type' => $error->isPersistent() ? 'error' : 'info',
                'code' => $error->getMessageKey(),
                'content' => $error->getMessage(),
                'severity' => $this->mapSeverity($error),
            ];
        }

        return $messages;
    }

    private function isUnrecoverable(Error $error): bool
    {
        return $error->getLevel() >= 20;
    }

    private function requiresBuyerInput(Error $error): bool
    {
        return $error->getLevel() >= 10 && $error->getLevel() < 20;
    }

    private function hasShippingAddress(SalesChannelContext $context): bool
    {
        if ($context->getCustomer() !== null) {
            return $context->getCustomer()->getActiveShippingAddress() !== null;
        }

        return $context->getShippingLocation()->getAddress() !== null;
    }

    private function hasPaymentMethod(SalesChannelContext $context): bool
    {
        return $context->getPaymentMethod()->getId() !== '';
    }

    private function mapSeverity(Error $error): string
    {
        return match (true) {
            $error->getLevel() >= 20 => 'unrecoverable',
            $error->getLevel() >= 10 => 'requires_buyer_input',
            default => 'recoverable',
        };
    }
}
