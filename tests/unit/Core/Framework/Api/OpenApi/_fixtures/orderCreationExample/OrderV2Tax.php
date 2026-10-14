<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-09-17 14:18:33
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tax amount calculated for one tax rate.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Tax extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Tax rate as a percentage.
         */
        #[Assert\NotNull]
        public float $rate,
        /**
         * Tax amount charged at this rate.
         */
        #[Assert\NotNull]
        public float $amount,
        /**
         * Portion of the order total to which this tax rate was applied.
         */
        #[Assert\NotNull]
        public float $taxableAmount,
    ) {
    }
}
