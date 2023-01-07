<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @package core
 *
 * @internal
 */
class Migration1651172963SetProductCmsPageToNullIfDefaultIsSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1651172963;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE product SET cms_page_id = null WHERE cms_page_id = :defaultCmsPageId;', ['defaultCmsPageId' => Uuid::fromHexToBytes(Defaults::CMS_PRODUCT_DETAIL_PAGE)]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
