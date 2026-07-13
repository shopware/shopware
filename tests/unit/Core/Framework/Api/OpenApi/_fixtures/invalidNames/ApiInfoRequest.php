<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApiInfoRequest
{
    public function __construct(
        /**
         * Type of the api
         */
        #[Assert\NotBlank]
        public string $type,
    ) {
    }
}
