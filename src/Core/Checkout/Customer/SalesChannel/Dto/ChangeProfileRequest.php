<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-13
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\Dto;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Make changes to a customer's account, like changing their name, salutation or title.
 */
#[Package('checkout')]
final readonly class ChangeProfileRequest
{
    public function __construct(
        /**
         * Customer first name. Value will be reused for shipping and billing address if not provided explicitly.
         */
        #[Assert\NotBlank]
        public string $firstName,
        /**
         * Customer last name. Value will be reused for shipping and billing address if not provided explicitly.
         */
        #[Assert\NotBlank]
        public string $lastName,
        /**
         * Id of the salutation for the customer account. Fetch options using `salutation` endpoint.
         */
        public ?string $salutationId = null,
        /**
         * (Academic) title of the customer
         */
        public ?string $title = null,
        /**
         * Birthday day
         */
        public ?int $birthdayDay = null,
        /**
         * Birthday month
         */
        public ?int $birthdayMonth = null,
        /**
         * Birthday year
         */
        public ?int $birthdayYear = null,
        /**
         * Type of the customer account. Default value is 'private'.
         */
        #[Assert\Choice(choices: ['private'])]
        public string $accountType = 'private',
        public mixed $company = null,
        public mixed $vatIds = null,
    ) {
    }
}
