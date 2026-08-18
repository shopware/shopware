<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads the VAT ID format patterns configured in Settings > Countries and matches VAT IDs against them,
 * either against the pattern of a single country or against the patterns of every EU member state.
 *
 * @internal
 */
#[Package('checkout')]
class VatIdPatternProvider implements ResetInterface
{
    /**
     * @var array<string, string>|null ISO code => VAT ID format pattern
     */
    private ?array $euPatterns = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * The pattern of every EU member state that has a usable one.
     *
     * @return array<string, string> ISO code => VAT ID format pattern
     */
    public function getEuPatterns(): array
    {
        return $this->euPatterns ??= $this->loadEuPatterns();
    }

    /**
     * @return string|null the ISO code of the member state the VAT ID belongs to, null if it belongs to none
     */
    public function matchEuVatId(string $vatId): ?string
    {
        foreach ($this->getEuPatterns() as $iso => $pattern) {
            if ($this->matches($pattern, $vatId)) {
                return $iso;
            }
        }

        return null;
    }

    /**
     * The VAT ID settings a merchant configured for a single country.
     *
     * @return array{checkPattern: bool, pattern: string|null}|null null if the country does not exist
     */
    public function getCountrySettings(string $countryId): ?array
    {
        $country = $this->connection->fetchAssociative(
            'SELECT `check_vat_id_pattern`, `vat_id_pattern` FROM `country` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($countryId)]
        );

        if ($country === false) {
            return null;
        }

        \assert(\array_key_exists('check_vat_id_pattern', $country));
        \assert(\array_key_exists('vat_id_pattern', $country));

        $pattern = (string) $country['vat_id_pattern'];

        return [
            'checkPattern' => (bool) $country['check_vat_id_pattern'],
            'pattern' => $pattern === '' ? null : $pattern,
        ];
    }

    public function matches(string $pattern, string $vatId): bool
    {
        return preg_match(self::toRegex($pattern), $vatId) === 1;
    }

    public function reset(): void
    {
        $this->euPatterns = null;
    }

    /**
     * @return array<string, string> ISO code => VAT ID format pattern
     */
    private function loadEuPatterns(): array
    {
        $sql = <<<'SQL'
            SELECT `iso`, `vat_id_pattern`
            FROM `country`
            WHERE `is_eu` = 1 AND `vat_id_pattern` IS NOT NULL AND `vat_id_pattern` != ''
            ORDER BY `iso`;
        SQL;

        /** @var list<array{iso: string, vat_id_pattern: string}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql);

        $patterns = [];
        foreach ($rows as $row) {
            // Merchants can edit the patterns, so they are not guaranteed to compile. A single broken
            // one would otherwise WARN on every VAT ID that has to be checked against the whole list.
            if (self::compiles($row['vat_id_pattern'])) {
                $patterns[$row['iso']] = $row['vat_id_pattern'];
            }
        }

        return $patterns;
    }

    private static function compiles(string $pattern): bool
    {
        return @preg_match(self::toRegex($pattern), '') !== false;
    }

    /**
     * The pattern is anchored, so a merchant pattern cannot match a substring of a longer VAT ID.
     */
    private static function toRegex(string $pattern): string
    {
        return '/^' . $pattern . '$/';
    }
}
