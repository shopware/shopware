<?php declare(strict_types=1);

// This file is auto-generated. Do not edit manually.

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Imitate the log in as a customer given a generated token.
 */
class JwtImpersonationPayloadDTO
{
    public function __construct(
        /** Generated customer impersonation JWT token. IMPORTANT: Flag v6.8.0.0 is required to use this version of the endpoint. */
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
