<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775460999AddParentNameToProductSearchConfig extends MigrationStep
{
    private const NAME_FIELD = 'name';

    private const PARENT_NAME_FIELD = 'parent.name';

    private const PARENT_NAME_RANKING = 500;

    public function getCreationTimestamp(): int
    {
        return 1775460999;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO `product_search_config_field` (`id`, `product_search_config_id`, `field`, `tokenize`, `searchable`, `ranking`, `created_at`)
                    SELECT
                        UNHEX(REPLACE(UUID(), "-", "")),
                        source.`product_search_config_id`,
                        :parentNameField,
                        source.`tokenize`,
                        source.`searchable`,
                        :ranking,
                        :createdAt
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
                'ranking' => self::PARENT_NAME_RANKING,
                'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
