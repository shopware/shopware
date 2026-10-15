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
 * Currency and rounding rules persisted with the order.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Currency extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * ISO 4217 currency code used by every amount in the order.
         */
        #[Assert\NotNull]
        #[Assert\Regex(pattern: '~^[A-Z]{3}$~')]
        public string $isoCode,
        /**
         * Currency symbol suitable for display.
         */
        #[Assert\NotNull]
        public string $symbol,
        /**
         * Rounding configuration applied to individual calculated items.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Rounding $itemRounding,
        /**
         * Rounding configuration applied to order totals.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Rounding $totalRounding,
    ) {
    }
}
