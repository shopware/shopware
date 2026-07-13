<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SalesChannelContextTaxRules
{
    public function __construct(
        #[Assert\NotNull]
        public float $taxRate,
        public ?string $name = null,
    ) {
    }
}
