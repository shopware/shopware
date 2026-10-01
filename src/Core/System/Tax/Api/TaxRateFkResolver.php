<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class TaxRateFkResolver extends AbstractFkResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getName(): string
    {
        return 'tax.tax_rate';
    }

    /**
     * @param array<FkReference> $map
     *
     * @return array<FkReference>
     */
    public function resolve(array $map): array
    {
        $rates = \array_map(static fn ($id) => (float) $id->value, $map);

        $rates = \array_values(\array_unique($rates, \SORT_NUMERIC));

        if ($rates === []) {
            return $map;
        }

        // tax_rate has no unique constraint — only resolve when exactly one tax matches the rate
        $hash = $this->connection->fetchAllKeyValue(
            'SELECT tax_rate, LOWER(HEX(MIN(id))) FROM tax WHERE tax_rate IN (:rates) GROUP BY tax_rate HAVING COUNT(id) = 1',
            ['rates' => $rates],
            ['rates' => ArrayParameterType::STRING]
        );

        foreach ($map as $reference) {
            $key = (string) (float) $reference->value;
            foreach ($hash as $rate => $id) {
                if ((string) (float) $rate === $key) {
                    $reference->resolved = $id;

                    break;
                }
            }
        }

        return $map;
    }
}
