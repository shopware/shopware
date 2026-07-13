<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PrivateRegistration
{
    public function __construct(
        /**
         * Email of the customer
         */
        #[Assert\NotBlank]
        public string $email,
        /**
         * Customer first name
         */
        #[Assert\NotBlank]
        public string $firstName,
        /**
         * Customer last name
         */
        #[Assert\NotBlank]
        public string $lastName,
        /**
         * Account type
         */
        #[Assert\Choice(choices: ['private'])]
        public string $accountType = 'private',
    ) {
    }
}
