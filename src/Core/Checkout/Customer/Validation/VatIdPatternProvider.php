<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Matches VAT IDs against the patterns configured in Settings > Countries, either against a single
 * country's pattern or against the patterns of every EU member state. Deciding about the
 * intra-community exemption additionally needs the seller's member state from Settings > Basic information.
 *
 * @internal
 */
#[Package('checkout')]
class VatIdPatternProvider implements ResetInterface
{
    private const SELLER_COUNTRY_CONFIG_KEY = 'core.basicInformation.sellerCountryId';

    /**
     * @var array<string, string>|null
     */
    private ?array $euPatterns = null;

    /**
     * @var array<string, string> ISO code => country id
     */
    private array $euCountryIds = [];

    /**
     * @var array<string, array{isEu: bool, checkPattern: bool, pattern: string|null}|null>
     */
    private array $countrySettings = [];

    /**
     * @param EntityRepository<CountryCollection> $countryRepository
     */
    public function __construct(
        private readonly EntityRepository $countryRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @return array<string, string> ISO code => VAT ID format pattern
     */
    public function getEuPatterns(): array
    {
        if ($this->euPatterns !== null) {
            return $this->euPatterns;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('isEu', true))
            ->addSorting(new FieldSorting('iso'));
        $criteria->setTitle('vat-id-pattern-provider::eu-countries');

        $countries = $this->countryRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $patterns = [];
        foreach ($countries as $country) {
            $iso = $country->getIso();
            if ($iso === null) {
                continue;
            }

            $this->euCountryIds[$iso] = $country->getId();

            $pattern = (string) $country->getVatIdPattern();

            // Merchants can edit the patterns, so they are not guaranteed to compile. A single broken
            // pattern would WARN on every VAT ID that has to be checked against the whole list.
            if ($pattern !== '' && $this->compiles($pattern)) {
                $patterns[$iso] = $pattern;
            }
        }

        return $this->euPatterns = $patterns;
    }

    public function reset(): void
    {
        $this->euPatterns = null;
        $this->euCountryIds = [];
        $this->countrySettings = [];
    }

    /**
     * @param string|null $salesChannelId null to only validate the format instead of deciding about tax
     */
    public function acceptsVatId(string $vatId, string $countryPattern, bool $isEu, ?string $salesChannelId): bool
    {
        if ($this->matches($countryPattern, $vatId)) {
            return true;
        }

        return $isEu && $this->isIntraCommunityVatId($vatId, $salesChannelId);
    }

    /**
     * @param string|null $salesChannelId null to only validate the format instead of deciding about tax
     */
    public function isIntraCommunityVatId(string $vatId, ?string $salesChannelId): bool
    {
        $state = $this->getStateByEuVatId($vatId);

        if ($state === null) {
            return false;
        }

        // Format validation is not a tax decision, so no member state has to be kept out
        if ($salesChannelId === null) {
            return true;
        }

        $sellerState = $this->getSellerState($salesChannelId);
        // Without a seller state a domestic supply is indistinguishable from an intra-community one
        if ($sellerState === null) {
            return false;
        }

        return $state !== $sellerState;
    }

    public function isDomesticSupply(?string $deliveryCountryIso, ?string $salesChannelId): bool
    {
        if ($deliveryCountryIso === null || $salesChannelId === null) {
            return false;
        }

        return $deliveryCountryIso === $this->getSellerState($salesChannelId);
    }

    /**
     * @param array<mixed>|null $vatIds
     */
    public function getCountryIdForVatIds(?array $vatIds): ?string
    {
        $vatId = array_values(array_filter($vatIds ?? []))[0] ?? null;

        if ($vatId === null) {
            return null;
        }

        $iso = $this->getStateByEuVatId((string) $vatId);

        return $iso === null ? null : $this->getEuCountryIds()[$iso];
    }

    /**
     * @return array{isEu: bool, checkPattern: bool, pattern: string|null}|null null if the country does not exist
     */
    public function getCountrySettings(string $countryId): ?array
    {
        if (\array_key_exists($countryId, $this->countrySettings)) {
            return $this->countrySettings[$countryId];
        }

        if (!Uuid::isValid($countryId)) {
            return null;
        }

        $criteria = new Criteria([$countryId]);
        $criteria->setTitle('vat-id-pattern-provider::country-settings');

        $country = $this->countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if ($country === null) {
            return $this->countrySettings[$countryId] = null;
        }

        $pattern = (string) $country->getVatIdPattern();

        return $this->countrySettings[$countryId] = [
            'isEu' => $country->getIsEu(),
            'checkPattern' => $country->getCheckVatIdPattern(),
            'pattern' => $pattern === '' ? null : $pattern,
        ];
    }

    public function matches(string $pattern, string $vatId): bool
    {
        // A pattern a merchant broke matches nothing instead of warning on every VAT ID
        return @preg_match($this->toRegex($pattern), $vatId) === 1;
    }

    /**
     * @return string|null the ISO code of the member state, null if the VAT ID belongs to none
     */
    private function getStateByEuVatId(string $vatId): ?string
    {
        foreach ($this->getEuPatterns() as $iso => $pattern) {
            if ($this->matches($pattern, $vatId)) {
                return $iso;
            }
        }

        return null;
    }

    /**
     * @return string|null the ISO code of the member state the seller supplies from, null when none is
     *                     configured or the configured country lies outside the EU
     */
    private function getSellerState(string $salesChannelId): ?string
    {
        if ($salesChannelId === '') {
            return null;
        }

        $countryId = $this->systemConfigService->getString(self::SELLER_COUNTRY_CONFIG_KEY, $salesChannelId);

        if ($countryId === '') {
            return null;
        }

        $iso = array_search(strtolower($countryId), $this->getEuCountryIds(), true);

        return $iso === false ? null : $iso;
    }

    /**
     * @return array<string, string> ISO code => country id
     */
    private function getEuCountryIds(): array
    {
        $this->getEuPatterns();

        return $this->euCountryIds;
    }

    private function compiles(string $pattern): bool
    {
        return @preg_match($this->toRegex($pattern), '') !== false;
    }

    private function toRegex(string $pattern): string
    {
        return '/^' . $pattern . '$/';
    }
}
