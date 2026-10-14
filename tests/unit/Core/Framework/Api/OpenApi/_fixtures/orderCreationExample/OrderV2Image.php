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
 * Image used to represent a line item in the order confirmation.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Image extends AbstractDto
{
    /**
     * Alternative text for the image, or `null` when none was defined.
     */
    public ?string $altText;

    /**
     * @internal
     */
    public function __construct(
        /**
         * Public URL of the image.
         */
        #[Assert\NotBlank]
        #[Assert\Url]
        public string $url,
    ) {
    }
}
