<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final readonly class SalesChannelContextItemRounding
{
    public function __construct(
        #[Assert\NotNull]
        public int $decimals,
        #[Assert\NotNull]
        public float $interval,
        #[Assert\NotNull]
        public bool $roundForNet,
    ) {
    }
}
