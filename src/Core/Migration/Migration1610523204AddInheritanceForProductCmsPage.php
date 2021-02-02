<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610523204AddInheritanceForProductCmsPage extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1610523204;
    }

    public function update(Connection $connection): void
    {
        $this->updateInheritance($connection, 'product', 'cmsPage');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
