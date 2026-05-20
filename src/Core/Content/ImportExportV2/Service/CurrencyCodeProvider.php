<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * Small cached lookup for currency ids and ISO codes used by import/export
 * path handling.
 *
 * Can move to a more generic namespace, could not find an existing service that does this currently.
 *
 * `PriceField` paths such as `price.EUR.net` need to resolve:
 * - `EUR` -> currency id on import
 * - currency id -> `EUR` on export
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CurrencyCodeProvider
{
    /**
     * @var array<string, string>|null
     */
    private ?array $currencyIdsByCode = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $currencyCodesById = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getCurrencyIdByCode(string $currencyCode): ?string
    {
        $this->loadMappings();

        return $this->currencyIdsByCode[strtoupper($currencyCode)] ?? null;
    }

    public function hasCurrencyCode(string $currencyCode): bool
    {
        $this->loadMappings();

        return isset($this->currencyIdsByCode[strtoupper($currencyCode)]);
    }

    public function getCodeForCurrencyId(string $currencyId): ?string
    {
        $this->loadMappings();

        return $this->currencyCodesById[strtolower($currencyId)] ?? null;
    }

    private function loadMappings(): void
    {
        if ($this->currencyIdsByCode !== null && $this->currencyCodesById !== null) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative('
            SELECT LOWER(HEX(id)) AS id, iso_code
            FROM currency
        ');

        $currencyIdsByCode = [];
        $currencyCodesById = [];

        foreach ($rows as $row) {
            $currencyId = $row['id'] ?? null;
            $currencyCode = $row['iso_code'] ?? null;

            if (!\is_string($currencyId) || $currencyId === '' || !\is_string($currencyCode) || $currencyCode === '') {
                continue;
            }

            $normalizedCode = strtoupper($currencyCode);
            $normalizedId = strtolower($currencyId);

            $currencyIdsByCode[$normalizedCode] = $normalizedId;
            $currencyCodesById[$normalizedId] = $normalizedCode;
        }

        $this->currencyIdsByCode = $currencyIdsByCode;
        $this->currencyCodesById = $currencyCodesById;
    }
}
