<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Error
{
    public function __construct(
        #[Assert\NotBlank]
        public string $code,
        #[Assert\NotBlank]
        public string $message,
    ) {
    }
}
