<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1572858066UpdateDefaultCategorySeoUrlTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572858066;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'UPDATE `seo_url_template` 
                    SET `template` = "{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}" 
                    WHERE `route_name` = "frontend.navigation.page" AND `template` = "{% for part in breadcrumb %}{{ part }}/{% endfor %}"'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
