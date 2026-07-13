<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Product detail
 */
final readonly class ReadProductResponse
{
    public function __construct(
        #[Assert\Valid]
        public ?Product $product = null,
    ) {
    }
}
