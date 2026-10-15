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
 * Payment or shipping method selected for the order.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Method extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Identifier of the selected method.
         */
        #[Assert\NotNull]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * Translated method name in the response language selected by `sw-language-id`, resolved through Shopware's language fallback chain.
         */
        #[Assert\NotNull]
        public string $displayName,
    ) {
    }
}
