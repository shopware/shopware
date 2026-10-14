<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-09-17 14:18:33
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input for creating an order from the current Store API cart. The cart, customer, addresses, currency, shipping method, and payment method come from the authenticated sales-channel context.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class CreateOrderV2Request extends AbstractRequest
{
    /**
     * Comment supplied by the customer and stored with the order.
     */
    public string $customerComment;

    /**
     * Marketing attribution stored with the order.
     */
    #[Assert\Valid]
    public CreateOrderV2RequestAttribution $attribution;

    /**
     * @internal
     */
    public function __construct(
        /**
         * Hash from the latest `GET /checkout/cart` response. Order creation fails with HTTP 409 when the current calculated cart no longer matches this value.
         */
        #[Assert\NotBlank]
        #[Assert\Length(min: 1)]
        public string $cartHash,
    ) {
    }
}
