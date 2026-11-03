<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payment transaction workflow state. Integrations must use `technicalName` for logic and `name` only for presentation.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2PaymentStatus extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Technical name of the payment state as defined by Shopware's `order_transaction.state` state machine.
         */
        #[Assert\NotNull]
        #[Assert\Choice(choices: ['open', 'paid', 'paid_partially', 'refunded', 'refunded_partially', 'cancelled', 'reminded', 'authorized', 'failed', 'in_progress', 'chargeback', 'unconfirmed'])]
        public string $technicalName,
        /**
         * Translated payment-state name in the response language selected by `sw-language-id`, resolved through Shopware's language fallback chain. Its value is configuration-dependent and is not a technical identifier.
         */
        #[Assert\NotNull]
        public string $name,
    ) {
    }
}
