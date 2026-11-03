<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Postal address captured when the order was placed.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class OrderV2Address extends AbstractDto
{
    /**
     * Company name, or `null` when not supplied.
     */
    public ?string $company;

    /**
     * Company department, or `null` when not supplied.
     */
    public ?string $department;

    /**
     * Customer title, or `null` when not supplied.
     */
    public ?string $title;

    /**
     * First optional additional address line, or `null` when not supplied.
     */
    public ?string $additionalAddressLine1;

    /**
     * Second optional additional address line, or `null` when not supplied.
     */
    public ?string $additionalAddressLine2;

    /**
     * Technical code of the country subdivision, or `null` when no subdivision applies.
     */
    public ?string $countryStateCode;

    /**
     * Translated country-subdivision name, or `null` when no subdivision applies.
     */
    public ?string $countryStateName;

    /**
     * Recipient phone number, or `null` when not supplied.
     */
    public ?string $phoneNumber;

    /**
     * @internal
     */
    public function __construct(
        /**
         * First name of the address recipient.
         */
        #[Assert\NotNull]
        public string $firstName,
        /**
         * Last name of the address recipient.
         */
        #[Assert\NotNull]
        public string $lastName,
        /**
         * Street name and house number.
         */
        #[Assert\NotNull]
        public string $street,
        /**
         * Postal or ZIP code.
         */
        #[Assert\NotNull]
        public string $postalCode,
        /**
         * City or locality.
         */
        #[Assert\NotNull]
        public string $city,
        /**
         * ISO 3166-1 alpha-2 country code.
         */
        #[Assert\Regex(pattern: '~^[A-Z]{2}$~')]
        public ?string $countryCode,
        /**
         * Translated country name for display.
         */
        #[Assert\NotNull]
        public string $countryName,
    ) {
    }
}
