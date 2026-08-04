<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer\VariantListingUpdaterTest
 */
#[Package('framework')]
class VariantListingUpdater
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string> $ids
     *
     * @throws Exception
     */
    public function update(array $ids, Context $context): void
    {
        $ids = array_filter($ids);

        if ($ids === []) {
            return;
        }

        $ids = array_keys(array_flip($ids));

        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $listingConfiguration = $this->getListingConfiguration($ids, $context);

        $displayParent = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product SET display_group = SHA2(HEX(product.id), 256) WHERE product.id = :id AND product.version_id = :versionId')
        );

        $hideParent = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product SET display_group = NULL WHERE product.id = :id AND product.version_id = :versionId')
        );

        $singleVariant = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product SET display_group = SHA2(HEX(product.parent_id), 256) WHERE product.parent_id = :id AND product.version_id = :versionId')
        );

        $displayGroupMapping = $this->connection->fetchAllKeyValue(
            'SELECT id, display_group FROM product WHERE id IN (:ids) AND version_id = :versionId',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'versionId' => $versionBytes,
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        // Number of variants per parent whose display_group does not match the value the
        // `$singleVariant` statement would write. Counting in SQL is required: a parent has many
        // variants and they can hold diverging values, so a single sampled row cannot tell us
        // whether the update is still needed. `<=>` is the NULL safe comparison.
        $outdatedVariantCount = $this->connection->fetchAllKeyValue(
            'SELECT parent_id, COUNT(*) FROM product
                WHERE parent_id IN (:ids)
                    AND version_id = :versionId
                    AND NOT (display_group <=> SHA2(HEX(parent_id), 256))
                GROUP BY parent_id',
            [
                'ids' => array_keys($listingConfiguration),
                'versionId' => $versionBytes,
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        foreach ($listingConfiguration as $parentId => $config) {
            $parentId = (string) $parentId;
            $currentDisplayGroup = $displayGroupMapping[$parentId] ?? null;
            // must mirror the `SHA2(HEX(...), 256)` expressions used by the update statements above
            $displayGroupValue = Hasher::hash(strtoupper(Uuid::fromBytesToHex($parentId)), 'sha256');

            $childCount = (int) $config['child_count'];
            $groups = $config['groups'];

            if ($config['main_variant'] || $config['display_parent']) {
                $groups = [];
            }

            if ($childCount <= 0) {
                // display parent in listing
                if ($currentDisplayGroup !== $displayGroupValue) {
                    $displayParent->execute(['id' => $parentId, 'versionId' => $versionBytes]);
                }
            } else {
                // hide parent
                if ($currentDisplayGroup !== null) {
                    $hideParent->execute(['id' => $parentId, 'versionId' => $versionBytes]);
                }
            }

            if ($groups === []) {
                // display single variant in listing
                if ((int) ($outdatedVariantCount[$parentId] ?? 0) > 0) {
                    $singleVariant->execute(['id' => $parentId, 'versionId' => $versionBytes]);
                }

                continue;
            }

            $query = $this->connection->createQueryBuilder();

            $query->from('(SELECT 1)', 'root');

            $fields = [];
            $params = ['parentId' => $parentId, 'versionId' => $versionBytes];
            // Positional index keeps SQL aliases and Doctrine parameter names unique and stable.
            foreach ($groups as $index => $groupId) {
                $mappingAlias = 'mapping' . $index;
                $optionAlias = 'option' . $index;

                $query->innerJoin('root', 'product_option', $mappingAlias, $mappingAlias . '.product_id IS NOT NULL');
                $query->innerJoin($mappingAlias, 'property_group_option', $optionAlias, $optionAlias . '.id = ' . $mappingAlias . '.property_group_option_id AND ' . $optionAlias . '.property_group_id = :' . $optionAlias);
                $query->andWhere($mappingAlias . '.product_id = product.id AND ' . $mappingAlias . '.product_version_id = :versionId');

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

            RetryableQuery::retryable($this->connection, function () use ($sql, $params): void {
                $this->connection->executeStatement($sql, $params);
            });
        }
    }

    /**
     * @param array<string> $ids
     *
     * @throws Exception
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function getListingConfiguration(array $ids, Context $context): array
    {
        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $query = $this->connection->createQueryBuilder();
        $query->select(
            'product.id as id',
            'product.variant_listing_config as config',
            '(SELECT COUNT(id) FROM product as child WHERE product.id = child.parent_id) as child_count',
        );
        $query->from('product');
        $query->andWhere('product.version_id = :version');
        $query->andWhere('product.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), ArrayParameterType::BINARY);
        $query->setParameter('version', $versionBytes);

        $configuration = $query->executeQuery()->fetchAllAssociative();

        $listingConfiguration = [];
        foreach ($configuration as $row) {
            $decodedConfig = $this->decodeVariantListingConfig($row['config'] ?? null);

            $groups = [];
            $groupConfig = $decodedConfig['configuratorGroupConfig'] ?? [];
            if (\is_array($groupConfig)) {
                foreach ($groupConfig as $group) {
                    if (!\is_array($group)
                        || !\array_key_exists('expressionForListings', $group)
                        || $group['expressionForListings'] !== true
                        || !\is_string($group['id'])) {
                        continue;
                    }

                    $groupId = strtolower($group['id']);
                    if (!Uuid::isValid($groupId)) {
                        continue;
                    }

                    $groups[] = $groupId;
                }
            }

            $listingConfiguration[$row['id']] = [
                'groups' => $groups,
                'child_count' => $row['child_count'] ?? null,
                'main_variant' => $decodedConfig['mainVariantId'] ?? null,
                'display_parent' => $decodedConfig['displayParent'] ?? null,
            ];
        }

        return $listingConfiguration;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeVariantListingConfig(mixed $raw): array
    {
        if (!\is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }
}
