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
 * Rules used to round monetary amounts.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Rounding extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Number of decimal places retained when rounding.
         */
        #[Assert\NotNull]
        public int $decimals,
        /**
         * Smallest currency increment to which an amount is rounded.
         */
        #[Assert\NotNull]
        public float $interval,
    ) {
    }
}
