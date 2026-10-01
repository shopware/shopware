<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775460999AddParentNameToProductSearchConfig extends MigrationStep
{
    private const NAME_FIELD = 'name';

    private const PARENT_NAME_FIELD = 'parent.name';

    private const PARENT_NAME_RANKING_FACTOR = 0.8;

    public function getCreationTimestamp(): int
    {
        return 1775460999;
    }

    public function update(Connection $connection): void
    {
        $createdAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $configs = $connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    source.`product_search_config_id`,
                    source.`tokenize`,
                    source.`ranking`
                FROM `product_search_config_field` source
                LEFT JOIN `product_search_config_field` existing
                    ON existing.`product_search_config_id` = source.`product_search_config_id`
                    AND existing.`field` = :parentNameField
                WHERE source.`field` = :nameField
                    AND existing.`id` IS NULL
            SQL,
            [
                'nameField' => self::NAME_FIELD,
                'parentNameField' => self::PARENT_NAME_FIELD,
            ]
        );

        $queue = new MultiInsertQueryQueue($connection);

        foreach ($configs as $config) {
            $queue->addInsert('product_search_config_field', [
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $config['product_search_config_id'],
                'field' => self::PARENT_NAME_FIELD,
                'tokenize' => $config['tokenize'],
                'searchable' => 0,
                'ranking' => (int) round((float) $config['ranking'] * self::PARENT_NAME_RANKING_FACTOR),
                'created_at' => $createdAt,
            ]);
        }

        $queue->execute();
    }
}
