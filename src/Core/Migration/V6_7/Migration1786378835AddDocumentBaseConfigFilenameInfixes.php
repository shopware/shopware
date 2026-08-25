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

        // Seed the ZUGFeRD embedded PDF infix for the core document types that actually support it
        $connection->executeStatement(<<<'SQL'
            UPDATE `document_base_config` AS `config`
            INNER JOIN `document_type` AS `type` ON `type`.`id` = `config`.`document_type_id`
            SET `config`.`filename_infixes` = '{"zugferd_embedded_pdf": "_zugferd"}'
            WHERE `type`.`technical_name` IN ('invoice', 'credit_note', 'storno')
              AND `config`.`filename_infixes` IS NULL
        SQL);
    }
}
