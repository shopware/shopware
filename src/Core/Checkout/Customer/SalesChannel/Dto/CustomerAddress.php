<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-13 15:04:47
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\Dto;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Added since version: 6.0.0.0
 */
#[Package('checkout')]
final readonly class CustomerAddress
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $id,
        /**
         * Unique identity of customer.
         */
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $customerId,
        /**
         * Unique identity of country.
         */
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $countryId,
        /**
         * First name of the customer.
         */
        #[Assert\NotBlank]
        public string $firstName,
        /**
         * Last name of the customer.
         */
        #[Assert\NotBlank]
        public string $lastName,
        /**
         * Name of customer's city.
         */
        #[Assert\NotBlank]
        public string $city,
        /**
         * Name of customer's street.
         */
        #[Assert\NotBlank]
        public string $street,
        /**
         * Unique identity of country's state.
         */
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public ?string $countryStateId = null,
        /**
         * Unique identity of salutation.
         */
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public ?string $salutationId = null,
        /**
         * Postal or zip code of customer's address.
         */
        public ?string $zipcode = null,
        /**
         * Name of customer's company.
         */
        public ?string $company = null,
        /**
         * Name of customer's department.
         */
        public ?string $department = null,
        /**
         * Titles given to customer like Dr. , Prof., etc
         */
        public ?string $title = null,
        /**
         * Customer's phone number.
         */
        public ?string $phoneNumber = null,
        /**
         * Additional customer's address information.
         */
        public ?string $additionalAddressLine1 = null,
        /**
         * Additional customer's address information.
         */
        public ?string $additionalAddressLine2 = null,
        /**
         * Runtime field, cannot be used as part of the criteria.
         */
        public ?string $hash = null,
        /**
         * @var array<string, mixed>
         */
        public ?array $customFields = null,
        /**
         * Added since version: 6.7.7.0. Runtime field, cannot be used as part of the criteria.
         */
        public ?bool $isDefaultBillingAddress = null,
        /**
         * Added since version: 6.7.7.0. Runtime field, cannot be used as part of the criteria.
         */
        public ?bool $isDefaultShippingAddress = null,
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public ?string $createdAt = null,
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public ?string $updatedAt = null,
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
