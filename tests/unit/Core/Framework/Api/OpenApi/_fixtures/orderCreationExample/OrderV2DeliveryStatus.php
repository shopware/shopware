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
 * Delivery workflow state. Integrations must use `technicalName` for logic and `name` only for presentation.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2DeliveryStatus extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Technical name of the delivery state as defined by Shopware's `order_delivery.state` state machine.
         */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['open', 'shipped_partially', 'shipped', 'returned', 'returned_partially', 'cancelled'])]
        public string $technicalName,
        /**
         * Translated delivery-state name in the response language selected by `sw-language-id`, resolved through Shopware's language fallback chain. Its value is configuration-dependent and is not a technical identifier.
         */
        #[Assert\NotBlank]
        public string $name,
    ) {
    }
}
