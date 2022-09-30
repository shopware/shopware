<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1664582400AddDocumentConfigDisplayingSalutationInPostalAddress;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1664582400AddDocumentConfigDisplayingSalutationInPostalAddress
 */
class Migration1664582400AddDocumentConfigDisplayingSalutationInPostalAddressTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testDefaultValueForIntraCommunityShouldBeInsertedCorrect(): void
    {
        $this->rollBackMigration();

        $documentBaseConfigs = $this->connection->fetchAll('SELECT `document_base_config`.`config` FROM `document_base_config`');

        static::assertNotEmpty($documentBaseConfigs);

        foreach ($documentBaseConfigs as $documentBaseConfig) {
            $invoiceConfig = json_decode($documentBaseConfig['config'] ?? '[]', true);

            static::assertArrayNotHasKey('displaySalutationInPostalAddress', $invoiceConfig);
        }

        $migration = new Migration1664582400AddDocumentConfigDisplayingSalutationInPostalAddress();
        $migration->update($this->connection);

        $documentBaseConfigs = $this->connection->fetchAll('SELECT `document_base_config`.`config` FROM `document_base_config`');

        static::assertNotEmpty($documentBaseConfigs);

        foreach ($documentBaseConfigs as $documentBaseConfig) {
            $invoiceConfig = json_decode($documentBaseConfig['config'] ?? '[]', true);

            static::assertArrayHasKey('displaySalutationInPostalAddress', $invoiceConfig);
            static::assertTrue($invoiceConfig['displaySalutationInPostalAddress']);
        }
    }

    private function rollBackMigration(): void
    {
        $documentBaseConfigs = $this->connection->fetchAll('SELECT `document_base_config`.`id`, `document_base_config`.`config` FROM `document_base_config`');

        foreach ($documentBaseConfigs as $documentBaseConfig) {
            $invoiceConfig = json_decode($documentBaseConfig['config'] ?? '[]', true);
            unset($invoiceConfig['displaySalutationInPostalAddress']);

            $this->connection->executeUpdate(
                'UPDATE `document_base_config` SET `config` = :invoiceData WHERE `id` = :documentConfigId',
                [
                    'invoiceData' => json_encode($invoiceConfig),
                    'documentConfigId' => $documentBaseConfig['id'],
                ]
            );
        }
    }
}
