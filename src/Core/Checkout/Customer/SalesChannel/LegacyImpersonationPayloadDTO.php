<?php declare(strict_types=1);

// This file is auto-generated. Do not edit manually.

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Imitate the log in as a customer given a generated token.
 */
class LegacyImpersonationPayloadDTO
{
    public function __construct(
        /** Generated customer impersonation token (legacy UUID token). */
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $token,
        /** ID of the customer. */
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $customerId,
        /** ID of the user who generated the token. */
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userId,
    ) {
    }
}
