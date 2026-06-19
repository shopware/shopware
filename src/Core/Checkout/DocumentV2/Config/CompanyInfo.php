<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class CompanyInfo
{
    public function __construct(
        public string $companyName,
        public string $companyStreet,
        public string $companyZipcode,
        public string $companyCity,
        public CountryEntity $companyCountry,
        public ?string $companyEmail = null,
        public ?string $companyPhone = null,
        public ?string $companyUrl = null,
        public ?string $executiveDirector = null,
        public ?string $taxNumber = null,
        public ?string $taxOffice = null,
        public ?string $vatId = null,
        public ?string $bankName = null,
        public ?string $bankIban = null,
        public ?string $bankBic = null,
        public ?string $placeOfJurisdiction = null,
        public ?string $placeOfFulfillment = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAddressParts(): array
    {
        return array_values(array_filter([
            $this->companyName,
            $this->companyStreet,
            $this->companyZipcode . ' ' . $this->companyCity,
            $this->companyCountry->getTranslation('name') ?? '',
        ], static fn (string $part): bool => \trim($part) !== ''));
    }
}
