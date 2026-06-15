<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer\VariantListingUpdaterTest;

/**
 * @codeCoverageIgnore
 *
 * @see VariantListingUpdaterTest
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

        foreach ($listingConfiguration as $parentId => $config) {
            $childCount = (int) $config['child_count'];
            $groups = $config['groups'];

            if ($config['main_variant'] || $config['display_parent']) {
                $groups = [];
            }

            if ($childCount <= 0) {
                // display parent in listing
                $displayParent->execute(['id' => $parentId, 'versionId' => $versionBytes]);
            } else {
                // hide parent
                $hideParent->execute(['id' => $parentId, 'versionId' => $versionBytes]);
            }

            if ($groups === []) {
                // display single variant in listing
                $singleVariant->execute(['id' => $parentId, 'versionId' => $versionBytes]);

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
