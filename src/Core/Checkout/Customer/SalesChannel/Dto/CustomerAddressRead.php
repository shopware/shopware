<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-13
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\Dto;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

#[Package('checkout')]
final readonly class CustomerAddressRead
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public string $customerId,
        #[Assert\NotBlank]
        #[Assert\DateTime(format: Defaults::STORAGE_DATE_TIME_FORMAT)]
        public string $createdAt,
        public ?string $updatedAt,
        /**
         * @var array<string, mixed>
         *
         * @todo Replace with the generated DTO once static schema files exist for generic entity definitions.
         */
        #[Assert\NotNull]
        public array $country,
        /**
         * @var array<string, mixed>
         *
         * @todo Replace with the generated DTO once static schema files exist for generic entity definitions.
         */
        #[Assert\NotNull]
        public array $salutation,
        #[Assert\Regex(pattern: '~^[0-9a-f]{32}$~')]
        public ?string $id = null,
        /**
         * @var array<string, mixed>
         *
         * @todo Replace with the generated DTO once static schema files exist for generic entity definitions.
         */
        public ?array $countryState = null,
    ) {
    }
}
