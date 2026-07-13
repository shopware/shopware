<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

namespace App\DTO;

/**
 * Customer group of the current user
 */
final readonly class SalesChannelContextCurrentCustomerGroup
{
    public function __construct(
        /**
         * Name of the group
         */
        public ?string $name = null,
        /**
         * Whether prices are displayed gross
         */
        public ?bool $displayGross = null,
    ) {
    }
}
