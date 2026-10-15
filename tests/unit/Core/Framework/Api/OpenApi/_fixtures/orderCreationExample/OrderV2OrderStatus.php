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
 * Order workflow state. Integrations must use `technicalName` for logic and `name` only for presentation.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2OrderStatus extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Technical name of the order state as defined by Shopware's `order.state` state machine.
         */
        #[Assert\NotNull]
        #[Assert\Choice(choices: ['open', 'in_progress', 'completed', 'cancelled'])]
        public string $technicalName,
        /**
         * Translated order-state name in the response language selected by `sw-language-id`, resolved through Shopware's language fallback chain. Its value is configuration-dependent and is not a technical identifier.
         */
        #[Assert\NotNull]
        public string $name,
    ) {
    }
}
