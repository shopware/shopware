<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1772007509ProductMainCategoryInheritance extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1772007509;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product', 'mainCategories')) {
            $this->updateInheritance($connection, 'product', 'mainCategories');
        }

        $this->registerIndexer($connection, 'product.indexer', ['product.inheritance']);
    }
}
