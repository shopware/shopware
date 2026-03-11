<?php declare(strict_types=1);

// This file is auto-generated. Do not edit manually.

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Logs in customers given their credentials.
 */
class LoginCustomerRequestDTO
{
    public function __construct(
        /** Email */
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $username,
        /** Password */
        #[Assert\NotBlank]
        public string $password,
    ) {
    }
}
