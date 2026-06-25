<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1782259200ContentLayoutRootSource extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782259200;
    }

    public function update(Connection $connection): void
    {
        // root_source is added NOT NULL with a '' default purely to satisfy an ALGORITHM=INSTANT NOT-NULL column
        // add on a table that is empty in this greenfield WIP; '' is never a valid root source (it is not in
        // RootSourceRegistry::knownRootSources()). Every DAL write supplies a real root_source (the field is
        // Required), and any raw-SQL / non-DAL writer MUST set one too: a stray '' row is rejected as a clean
        // unknownRootSource 400 (not a 500) on its next layout edit, because ContentLayoutWriteValidator re-checks
        // membership of the committed source. addColumn() keeps the add ALGORITHM=INSTANT and is a no-op when the
        // column already exists.
        $this->addColumn($connection, 'content_layout', 'root_source', 'VARCHAR(255)', false, '\'\'');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'content_layout', 'schema');
    }
}
