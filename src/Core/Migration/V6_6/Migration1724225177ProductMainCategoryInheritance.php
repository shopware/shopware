<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1724225177ProductMainCategoryInheritance extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1724225177;
    }

    public function update(Connection $connection): void
    {
        $this->updateInheritance($connection, 'product', 'mainCategories');
        $this->registerIndexer($connection, 'product.indexer', ['product.inheritance']);
    }
}
