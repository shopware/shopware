<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1773112488SetDefaultCmsPageIdForCategories extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773112488;
    }

    public function update(Connection $connection): void
    {
        $result = $connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => 'core.cms.default_category_cms_page']
        );

        if ($result === false) {
            return;
        }

        $decoded = json_decode((string) $result, true);
        $cmsPageId = $decoded['_value'] ?? null;

        if (!\is_string($cmsPageId) || $cmsPageId === '') {
            return;
        }

        $connection->executeStatement(
            'UPDATE `category` SET `cms_page_id` = :cmsPageId WHERE `cms_page_id` IS NULL',
            ['cmsPageId' => Uuid::fromHexToBytes($cmsPageId)]
        );
    }
}
