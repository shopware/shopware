<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775200002RecalculateProductDisplayGroupHash extends MigrationStep
{
    private const BATCH_SIZE = 500;

    public function getCreationTimestamp(): int
    {
        return 1775200002;
    }

    public function update(Connection $connection): void
    {
        $displayParentSql = 'UPDATE product SET display_group = SHA2(HEX(product.id), 256) WHERE product.id = :id AND product.version_id = :versionId';
        $hideParentSql = 'UPDATE product SET display_group = NULL WHERE product.id = :id AND product.version_id = :versionId';
        $singleVariantSql = 'UPDATE product SET display_group = SHA2(HEX(product.parent_id), 256) WHERE product.parent_id = :id AND product.version_id = :versionId';

        $lastAutoIncrement = 0;

        while (true) {
            $parents = $connection->fetchAllAssociative(
                <<<'SQL'
                SELECT
                    parent.id,
                    parent.version_id,
                    parent.variant_listing_config as config,
                    parent.auto_increment,
                    (
                        SELECT COUNT(child.id)
                        FROM product child
                        WHERE child.parent_id = parent.id
                          AND child.parent_version_id = parent.version_id
                    ) as child_count
                FROM product parent
                WHERE parent.parent_id IS NULL
                  AND parent.auto_increment > :lastAutoIncrement
                  AND (
                    parent.display_group IS NOT NULL
                    OR parent.variant_listing_config IS NOT NULL
                    OR EXISTS (
                        SELECT 1
                        FROM product child
                        WHERE child.parent_id = parent.id
                          AND child.parent_version_id = parent.version_id
                    )
                  )
                ORDER BY parent.auto_increment ASC
                LIMIT :limit
                SQL,
                ['lastAutoIncrement' => $lastAutoIncrement, 'limit' => self::BATCH_SIZE],
                ['lastAutoIncrement' => ParameterType::INTEGER, 'limit' => ParameterType::INTEGER]
            );

            if ($parents === []) {
                break;
            }

            foreach ($parents as $parent) {
                if (!isset($parent['id'], $parent['version_id'])) {
                    continue;
                }

                $parentId = $parent['id'];
                $versionId = $parent['version_id'];
                $childCount = (int) ($parent['child_count'] ?? 0);
                $lastAutoIncrement = (int) ($parent['auto_increment'] ?? $lastAutoIncrement);

                $config = $this->decodeConfig($parent['config'] ?? null);
                $groups = $this->extractListingGroups($config);

                if (($config['mainVariantId'] ?? null) || ($config['displayParent'] ?? null)) {
                    $groups = [];
                }

                if ($childCount <= 0) {
                    $connection->executeStatement($displayParentSql, ['id' => $parentId, 'versionId' => $versionId]);
                } else {
                    $connection->executeStatement($hideParentSql, ['id' => $parentId, 'versionId' => $versionId]);
                }

                if ($groups === []) {
                    $connection->executeStatement($singleVariantSql, ['id' => $parentId, 'versionId' => $versionId]);

                    continue;
                }

                $query = $connection->createQueryBuilder();
                $query->from('(SELECT 1)', 'root');

                $fields = [];
                $params = ['parentId' => $parentId, 'versionId' => $versionId];

                foreach ($groups as $index => $groupId) {
                    $mappingAlias = 'mapping' . $index;
                    $optionAlias = 'option' . $index;

                    $query->innerJoin('root', 'product_option', $mappingAlias, $mappingAlias . '.product_id IS NOT NULL');
                    $query->innerJoin(
                        $mappingAlias,
                        'property_group_option',
                        $optionAlias,
                        $optionAlias . '.id = ' . $mappingAlias . '.property_group_option_id AND ' . $optionAlias . '.property_group_id = :' . $optionAlias
                    );
                    $query->andWhere($mappingAlias . '.product_id = product.id');

                    $fields[] = 'LOWER(HEX(' . $optionAlias . '.id))';
                    $params[$optionAlias] = Uuid::fromHexToBytes($groupId);
                }

                $query->addSelect('CONCAT(' . implode(',', $fields) . ')');

                $sql = '
                UPDATE product SET display_group = SHA2(
                    CONCAT(
                        LOWER(HEX(product.parent_id)),
                        (' . $query->getSQL() . ')
                    ),
                    256
                ) WHERE parent_id = :parentId AND version_id = :versionId';

                $connection->executeStatement($sql, $params);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig(mixed $config): array
    {
        if (!\is_string($config) || $config === '') {
            return [];
        }

        try {
            $decoded = json_decode($config, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Core schema enforces JSON on this column; kept for imports without that constraint.
            return []; // @codeCoverageIgnore
        }

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private function extractListingGroups(array $config): array
    {
        $groups = [];
        $groupConfig = $config['configuratorGroupConfig'] ?? [];

        if (!\is_array($groupConfig)) {
            return [];
        }

        foreach ($groupConfig as $group) {
            if (!\is_array($group)
                || !\array_key_exists('expressionForListings', $group)
                || $group['expressionForListings'] !== true
                || !\is_string($group['id'])) {
                continue;
            }

            $groups[] = $group['id'];
        }

        return $groups;
    }
}
