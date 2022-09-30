<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1664582400AddDocumentConfigDisplayingSalutationInPostalAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1664582400;
    }

    public function update(Connection $connection): void
    {
        $this->addDisplaySalutationInPostalAddressIntoDocumentConfig($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addDisplaySalutationInPostalAddressIntoDocumentConfig(Connection $connection): void
    {
        $documentBaseConfigs = $connection->fetchAll('SELECT `document_base_config`.`id`, `document_base_config`.`config` FROM `document_base_config`');

        foreach ($documentBaseConfigs as $documentBaseConfig) {
            $invoiceConfig = json_decode($documentBaseConfig['config'] ?? '[]', true);
            $invoiceConfig['displaySalutationInPostalAddress'] = true;

            $connection->executeUpdate(
                'UPDATE `document_base_config` SET `config` = :invoiceData WHERE `id` = :documentConfigId',
                [
                    'invoiceData' => json_encode($invoiceConfig),
                    'documentConfigId' => $documentBaseConfig['id'],
                ]
            );
        }
    }
}
