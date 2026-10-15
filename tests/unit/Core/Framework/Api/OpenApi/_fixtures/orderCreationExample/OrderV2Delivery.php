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
 * Primary delivery created for the order.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Delivery extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Identifier of the primary order delivery.
         */
        #[Assert\NotNull]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * Current status of the primary delivery.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2DeliveryStatus $status,
        /**
         * Shipping method selected for the delivery.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Method $method,
        /**
         * Shipping address captured for the delivery.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2Address $shippingAddress,
        /**
         * Estimated delivery window calculated when the order was placed.
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderV2EstimatedDelivery $estimatedDelivery,
        /**
         * @var list<string> Carrier tracking codes assigned to the delivery. The array is empty until codes are available.
         */
        #[Assert\NotNull]
        #[Assert\All(new Assert\Type('string'))]
        public array $trackingCodes,
    ) {
    }
}
