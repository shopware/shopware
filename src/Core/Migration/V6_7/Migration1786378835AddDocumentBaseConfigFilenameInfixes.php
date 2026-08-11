<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1786378835AddDocumentBaseConfigFilenameInfixes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786378835;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'document_base_config', 'filename_infixes', 'JSON');
    }
}
