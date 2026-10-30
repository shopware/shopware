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
 * Primary payment transaction created for the order.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Payment extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Identifier of the primary order transaction.
         */
        #[Assert\NotNull]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $transactionId,
        /**
         * Current status of the primary payment transaction.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2PaymentStatus $status,
        /**
         * Payment method selected for the order.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Method $method,
    ) {
    }
}
