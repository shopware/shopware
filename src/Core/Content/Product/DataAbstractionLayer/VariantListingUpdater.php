<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
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

        if (empty($ids)) {
            return;
        }

        $ids = array_keys(array_flip($ids));

        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $listingConfiguration = $this->getListingConfiguration($ids, $context);

        $displayParent = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product SET display_group = MD5(HEX(product.id)) WHERE product.id = :id AND product.version_id = :versionId')
        );

        $hideParent = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product SET display_group = NULL WHERE product.id = :id AND product.version_id = :versionId')
        );

        $singleVariant = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product SET display_group = MD5(HEX(product.parent_id)) WHERE product.parent_id = :id AND product.version_id = :versionId')
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

            if (empty($groups)) {
                // display single variant in listing
                $singleVariant->execute(['id' => $parentId, 'versionId' => $versionBytes]);

                continue;
            }

            $query = $this->connection->createQueryBuilder();

            $query->from('(SELECT 1)', 'root');

            $fields = [];
            $params = ['parentId' => $parentId, 'versionId' => $versionBytes];
            foreach ($groups as $groupId) {
                $mappingAlias = 'mapping' . $groupId;
                $optionAlias = 'option' . $groupId;

                $query->innerJoin('root', 'product_option', $mappingAlias, $mappingAlias . '.product_id IS NOT NULL');
                $query->innerJoin($mappingAlias, 'property_group_option', $optionAlias, $optionAlias . '.id = ' . $mappingAlias . '.property_group_option_id AND ' . $optionAlias . '.property_group_id = :' . $optionAlias);
                $query->andWhere($mappingAlias . '.product_id = product.id');

                $fields[] = 'LOWER(HEX(' . $optionAlias . '.id))';

                $params[$optionAlias] = Uuid::fromHexToBytes($groupId);
            }

            $query->addSelect('CONCAT(' . implode(',', $fields) . ')');

            $sql = '
            UPDATE product SET display_group = MD5(
                CONCAT(
                    LOWER(HEX(product.parent_id)),
                    (' . $query->getSQL() . ')
                )
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
        $query->select([
            'product.id as id',
            'product.variant_listing_config as config',
            '(SELECT COUNT(id) FROM product as child WHERE product.id = child.parent_id) as child_count',
        ]);
        $query->from('product');
        $query->andWhere('product.version_id = :version');
        $query->andWhere('product.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), ArrayParameterType::STRING);
        $query->setParameter('version', $versionBytes);

        $configuration = $query->executeQuery()->fetchAllAssociative();

        $listingConfiguration = [];
        foreach ($configuration as $config) {
            $config['config'] = $config['config'] === null ? [] : json_decode((string) $config['config'], true, 512, \JSON_THROW_ON_ERROR);

            $groups = [];
            $configuratorGroupConfig = $config['config']['configuratorGroupConfig'] ?? [];
            foreach ($configuratorGroupConfig as $group) {
                if ($group['expressionForListings']) {
                    $groups[] = $group['id'];
                }
            }

            $listingConfiguration[$config['id']] = [
                'groups' => $groups,
                'child_count' => $config['child_count'] ?? null,
                'main_variant' => $config['config']['mainVariantId'] ?? null,
                'display_parent' => $config['config']['displayParent'] ?? null,
            ];
        }

        return $listingConfiguration;
    }
}
