<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Imitate the log in as a customer given a generated token.
 */
final readonly class JwtImpersonationPayload
{
    public function __construct(
        /**
         * Generated customer impersonation JWT token.
         */
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
