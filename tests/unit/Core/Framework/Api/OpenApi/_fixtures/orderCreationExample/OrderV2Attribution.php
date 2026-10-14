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

/**
 * Marketing attribution captured with the order.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Attribution extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Affiliate identifier attributed to the order, or `null` when none was supplied.
         */
        public ?string $affiliateCode,
        /**
         * Campaign identifier attributed to the order, or `null` when none was supplied.
         */
        public ?string $campaignCode,
    ) {
    }
}
