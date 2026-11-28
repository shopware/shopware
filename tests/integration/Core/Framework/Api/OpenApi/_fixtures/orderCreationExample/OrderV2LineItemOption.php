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
 * Translated variant option captured for a product line item.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2LineItemOption extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Translated option-group name, for example `Color`.
         */
        #[Assert\NotNull]
        public string $group,
        /**
         * Translated selected option name, for example `Blue`.
         */
        #[Assert\NotNull]
        public string $option,
    ) {
    }
}
