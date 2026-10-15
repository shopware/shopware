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
 * Display-ready monetary summary. All amounts use the order-level currency.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2PriceSummary extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Total of all line items, including discounts and excluding shipping.
         */
        #[Assert\NotNull]
        public float $subtotal,
        /**
         * Total shipping charge.
         */
        #[Assert\NotNull]
        public float $shipping,
        /**
         * Order total excluding tax.
         */
        #[Assert\NotNull]
        public float $net,
        /**
         * Total tax amount.
         */
        #[Assert\NotNull]
        public float $tax,
        /**
         * Final amount payable by the customer, including shipping and tax.
         */
        #[Assert\NotNull]
        public float $total,
        /**
         * Tax mode used to calculate the order.
         */
        #[Assert\NotNull]
        public OrderV2TaxStatus $taxStatus,
        /**
         * @var list<OrderV2Tax> Tax amounts grouped by tax rate.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public array $taxes,
    ) {
    }
}
