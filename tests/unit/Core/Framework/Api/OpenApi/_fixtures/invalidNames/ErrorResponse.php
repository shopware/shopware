<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ErrorResponse
{
    public function __construct(
        /**
         * @var list<Error>
         */
        #[Assert\Valid]
        public ?array $errors = null,
    ) {
    }
}
