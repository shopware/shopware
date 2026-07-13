<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07
 */

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BusinessRegistration
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
         * Company name
         */
        #[Assert\NotBlank]
        public string $company,
        /**
         * @var list<string> VAT IDs
         */
        #[Assert\NotNull]
        #[Assert\All(new Assert\Type('string'))]
        public array $vatIds,
        /**
         * Account type
         */
        #[Assert\Choice(choices: ['business'])]
        public string $accountType = 'business',
    ) {
    }
}
