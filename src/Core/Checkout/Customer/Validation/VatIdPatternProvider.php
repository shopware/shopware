<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads the VAT ID format patterns configured in Settings > Countries and matches VAT IDs against them,
 * either against the pattern of a single country or against the patterns of every EU member state.
 *
 * It also answers whether a VAT ID grants an intra-community exemption, which needs the seller's own
 * member state from Settings > Basic information on top of the patterns.
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

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * The pattern of every EU member state that has a usable one.
     *
     * @return array<string, string> ISO code => VAT ID format pattern
     */
    public function getEuPatterns(): array
    {
        if ($this->euPatterns !== null) {
            return $this->euPatterns;
        }

        $sql = <<<'SQL'
            SELECT `iso`, LOWER(HEX(`id`)) AS `id`, `vat_id_pattern`
            FROM `country`
            WHERE `is_eu` = 1
            ORDER BY `iso`;
        SQL;

        /** @var list<array{iso: string, id: string, vat_id_pattern: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql);

        $patterns = [];
        foreach ($rows as $row) {
            $this->euCountryIds[$row['iso']] = $row['id'];

            $pattern = (string) $row['vat_id_pattern'];

            // Merchants can edit the patterns, so they are not guaranteed to compile. A single broken
            // pattern would WARN on every VAT ID that has to be checked against the whole list.
            if ($pattern !== '' && $this->compiles($pattern)) {
                $patterns[$row['iso']] = $pattern;
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
     * Whether a country accepts a VAT ID: it matches the country's own pattern, or - inside the EU - it
     * identifies the customer in another member state. Both the tax decision and the format validation
     * ask the same question, so they ask it in one place.
     *
     * @param string|null $salesChannelId null when the caller is not deciding about tax, for example when
     *                                    it only validates the format a customer entered
     */
    public function acceptsVatId(string $vatId, string $countryPattern, bool $isEu, ?string $salesChannelId): bool
    {
        if ($this->matches($countryPattern, $vatId)) {
            return true;
        }

        return $isEu && $this->isIntraCommunityVatId($vatId, $salesChannelId);
    }

    /**
     * Whether a VAT ID identifies the customer in a member state other than the one the seller supplies
     * from, which is what Article 138 of the VAT Directive conditions the intra-community exemption on.
     * A VAT ID of the seller's own member state makes the supply a domestic one, so it grants no exemption.
     *
     * @param string|null $salesChannelId null when the caller is not deciding about tax, for example when
     *                                    it only validates the format a customer entered
     */
    public function isIntraCommunityVatId(string $vatId, ?string $salesChannelId): bool
    {
        $state = $this->getStateByEuVatId($vatId);

        if ($state === null) {
            return false;
        }

        // Validating the format a customer entered is not a tax decision, so there is no member state
        // to keep out and every one of them counts
        if ($salesChannelId === null) {
            return true;
        }

        $sellerState = $this->getSellerState($salesChannelId);
        // A shop that has no seller state cannot tell a domestic supply from an intra-community one, so
        // the exemption stays off until it is configured
        if ($sellerState === null) {
            return false;
        }

        return $state !== $sellerState;
    }

    /**
     * The member state a customer's VAT IDs belong to.
     *
     * A customer holds a list of VAT IDs while the storefront exposes exactly one input, and validation
     * already requires every entry to match some member state, so the first entry decides the country.
     *
     * @param array<mixed>|null $vatIds
     *
     * @return string|null the id of the member state, null when the list is empty or matches none
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
     * The VAT ID settings a merchant configured for a single country.
     *
     * @return array{isEu: bool, checkPattern: bool, pattern: string|null}|null null if the country does not exist
     */
    public function getCountrySettings(string $countryId): ?array
    {
        if (\array_key_exists($countryId, $this->countrySettings)) {
            return $this->countrySettings[$countryId];
        }

        $country = $this->connection->fetchAssociative(
            'SELECT `is_eu`, `check_vat_id_pattern`, `vat_id_pattern` FROM `country` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($countryId)]
        );

        if ($country === false) {
            return $this->countrySettings[$countryId] = null;
        }

        \assert(\array_key_exists('is_eu', $country));
        \assert(\array_key_exists('check_vat_id_pattern', $country));
        \assert(\array_key_exists('vat_id_pattern', $country));

        $pattern = (string) $country['vat_id_pattern'];

        return $this->countrySettings[$countryId] = [
            'isEu' => (bool) $country['is_eu'],
            'checkPattern' => (bool) $country['check_vat_id_pattern'],
            'pattern' => $pattern === '' ? null : $pattern,
        ];
    }

    public function matches(string $pattern, string $vatId): bool
    {
        // A pattern a merchant broke matches nothing, rather than spams a warning on every
        // VAT ID it is checked against
        return @preg_match($this->toRegex($pattern), $vatId) === 1;
    }

    /**
     * @return string|null the ISO code of the member state the VAT ID belongs to, null if it belongs to none
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
     * @return string|null the ISO code of the member state the seller supplies from, null when the shop
     *                     configured none or configured a country outside the EU, which cannot supply
     *                     intra-community in the first place
     */
    private function getSellerState(string $salesChannelId): ?string
    {
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

    /**
     * The pattern is anchored, so a merchant pattern cannot match a substring of a longer VAT ID.
     */
    private function toRegex(string $pattern): string
    {
        return '/^' . $pattern . '$/';
    }
}
