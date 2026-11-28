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
 * Language persisted with the order and used for translated display values in this response.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Language extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Identifier of the language persisted with the order.
         */
        #[Assert\NotNull]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * IETF BCP 47 locale code, for example `en-GB` or `de-DE`.
         */
        #[Assert\NotNull]
        public string $localeCode,
    ) {
    }
}
