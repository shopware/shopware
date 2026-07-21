<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class SalutationKeyFkResolver extends AbstractFkResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getName(): string
    {
        return 'salutation.salutation_key';
    }

    /**
     * @param array<FkReference> $map
     *
     * @return array<FkReference>
     */
    public function resolve(array $map): array
    {
        $keys = \array_map(static fn ($id) => $id->value, $map);

        $keys = \array_filter(\array_unique($keys));

        if ($keys === []) {
            return $map;
        }

        $hash = $this->connection->fetchAllKeyValue(
            'SELECT salutation_key, LOWER(HEX(id)) FROM salutation WHERE salutation_key IN (:keys)',
            ['keys' => $keys],
            ['keys' => ArrayParameterType::STRING]
        );

        foreach ($map as $reference) {
            if (isset($hash[$reference->value])) {
                $reference->resolved = $hash[$reference->value];
            }
        }

        return $map;
    }
}
