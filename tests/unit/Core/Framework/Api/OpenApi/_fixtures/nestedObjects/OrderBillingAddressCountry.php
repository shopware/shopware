<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

namespace App\DTO;

/**
 * Country details
 */
final readonly class OrderBillingAddressCountry
{
    public function __construct(
        /**
         * ISO 3166-1 alpha-2 code
         */
        public ?string $iso = null,
        public ?string $name = null,
    ) {
    }
}
