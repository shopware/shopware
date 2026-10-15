<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Estimated time window in which the delivery should arrive.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2EstimatedDelivery extends AbstractDto
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Earliest estimated delivery date and time.
         */
        #[Assert\NotNull]
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public string $earliest,
        /**
         * Latest estimated delivery date and time.
         */
        #[Assert\NotNull]
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public string $latest,
    ) {
    }
}
