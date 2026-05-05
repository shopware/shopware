<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class LocaleCodeFkResolver extends AbstractFkResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getName(): string
    {
        return 'locale.code';
    }

    /**
     * @param array<FkReference> $map
     *
     * @return array<FkReference>
     */
    public function resolve(array $map): array
    {
        $codes = \array_map(static fn ($id) => $id->value, $map);

        $codes = \array_filter(\array_unique($codes));

        if ($codes === []) {
            return $map;
        }

        $hash = $this->connection->fetchAllKeyValue(
            'SELECT code, LOWER(HEX(id)) FROM locale WHERE code IN (:codes)',
            ['codes' => $codes],
            ['codes' => ArrayParameterType::STRING]
        );

        foreach ($map as $reference) {
            if (isset($hash[$reference->value])) {
                $reference->resolved = $hash[$reference->value];
            }
        }

        return $map;
    }
}
