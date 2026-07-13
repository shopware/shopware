<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * An order entity
 */
final readonly class Order
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * The billing address
         */
        #[Assert\NotNull]
        #[Assert\Valid]
        public OrderBillingAddress $billingAddress,
    ) {
    }
}
