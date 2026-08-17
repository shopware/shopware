<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Provides the VAT ID format patterns of every EU member state, so a VAT ID can be checked against
 * all of them, instead of a single country.
 *
 * @internal
 */
#[Package('checkout')]
class EuVatIdPatternProvider implements ResetInterface
{
    /**
     * @var list<VatIdPattern>|null
     */
    private ?array $patterns = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<VatIdPattern>
     */
    public function getPatterns(): array
    {
        return $this->patterns ??= $this->loadPatterns();
    }

    public function matchVatId(string $vatId): ?VatIdPattern
    {
        foreach ($this->getPatterns() as $pattern) {
            if ($pattern->matches($vatId)) {
                return $pattern;
            }
        }

        return null;
    }

    public function reset(): void
    {
        $this->patterns = null;
    }

    /**
     * @return list<VatIdPattern>
     */
    private function loadPatterns(): array
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
            $pattern = new VatIdPattern($row['iso'], $row['vat_id_pattern']);

            // A single broken pattern will WARN on every invalid VAT ID.
            if ($pattern->isValid()) {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }
}
