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
 * Added since version: 6.0.0.0
 */
#[Package('checkout')]
final readonly class CreateCustomerAddressRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $countryId,
        #[Assert\NotBlank]
        public string $firstName,
        #[Assert\NotBlank]
        public string $lastName,
        #[Assert\NotBlank]
        public string $city,
        #[Assert\NotBlank]
        public string $street,
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public ?string $countryStateId = null,
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public ?string $salutationId = null,
        public ?string $zipcode = null,
        public ?string $company = null,
        public ?string $department = null,
        public ?string $title = null,
        public ?string $phoneNumber = null,
        public ?string $additionalAddressLine1 = null,
        public ?string $additionalAddressLine2 = null,
        /**
         * @var array<string, mixed>
         */
        public ?array $customFields = null,
        /**
         * @var array<string, mixed>
         *
         * @todo Replace with the generated DTO once static schema files exist for generic entity definitions.
         */
        public ?array $country = null,
        /**
         * @var array<string, mixed>
         *
         * @todo Replace with the generated DTO once static schema files exist for generic entity definitions.
         */
        public ?array $countryState = null,
        /**
         * @var array<string, mixed>
         *
         * @todo Replace with the generated DTO once static schema files exist for generic entity definitions.
         */
        public ?array $salutation = null,
    ) {
    }
}
