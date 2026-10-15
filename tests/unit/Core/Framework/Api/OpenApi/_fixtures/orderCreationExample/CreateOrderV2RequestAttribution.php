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

/**
 * Marketing attribution stored with the order.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class CreateOrderV2RequestAttribution extends AbstractDto
{
    /**
     * Affiliate identifier used to attribute the order to a referring partner.
     */
    public string $affiliateCode;

    /**
     * Campaign identifier used to attribute the order to a marketing campaign.
     */
    public string $campaignCode;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
