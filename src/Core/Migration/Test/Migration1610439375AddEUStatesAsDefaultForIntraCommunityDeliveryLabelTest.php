<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1610439375AddEUStatesAsDefaultForIntraCommunityDeliveryLabel;

class Migration1610439375AddEUStatesAsDefaultForIntraCommunityDeliveryLabelTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testDefaultValueForIntraCommunityShouldBeInsertedCorrect(): void
    {
        $this->rollBackMigration();
        $this->createDocumentBaseConfigDataTest();

        $listInvoiceData = $this->getListInvoiceData();
        foreach ($listInvoiceData as $invoiceData) {
            $invoiceConfig = json_decode($invoiceData['config'] ?? '[]', true);
            unset($invoiceConfig['deliveryCountries']);

            $this->connection->executeUpdate(
                'UPDATE `document_base_config` SET `config` = :invoiceData WHERE `id` = :documentConfigId',
                [
                    'invoiceData' => json_encode($invoiceConfig),
                    'documentConfigId' => $invoiceData['id'],
                ]
            );
            static::assertFalse(isset($invoiceData['deliveryCountries']));
        }
        $migration = new Migration1610439375AddEUStatesAsDefaultForIntraCommunityDeliveryLabel();
        $migration->update($this->connection);

        $listInvoiceData = $this->getListInvoiceData();

        $euStates = $this->connection->executeQuery(
            "SELECT `id` FROM `country` WHERE `iso`
                IN ('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'GR', 'ES', 'FI', 'FR', 'GB', 'HU', 'IE', 'IT',
                'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'HR')"
        )->fetchAll(FetchMode::COLUMN);

        foreach ($listInvoiceData as $invoiceData) {
            $invoiceConfig = json_decode($invoiceData['config'], true);

            $actual = $invoiceConfig['deliveryCountries'];
            sort($actual);

            $expected = Uuid::fromBytesToHexList($euStates);
            sort($expected);

            static::assertEquals($expected, $actual);
        }
    }

    private function rollBackMigration(): void
    {
        $listInvoiceData = $this->getListInvoiceData();

        foreach ($listInvoiceData as $invoiceData) {
            $invoiceConfig = json_decode($invoiceData['config'] ?? '[]', true);
            unset($invoiceConfig['deliveryCountries']);

            $this->connection->executeUpdate(
                'UPDATE `document_base_config` SET `config` = :invoiceData WHERE `id` = :documentConfigId',
                [
                    'invoiceData' => json_encode($invoiceConfig),
                    'documentConfigId' => $invoiceData['id'],
                ]
            );
        }
    }

    private function getListInvoiceData()
    {
        return $this->connection->fetchAll(
            'SELECT `document_base_config`.`id`, `document_base_config`.`config` FROM `document_base_config`
            LEFT JOIN `document_type` ON `document_base_config`.`document_type_id` = `document_type`.`id`
            WHERE `document_type`.`technical_name` = :documentName',
            ['documentName' => InvoiceGenerator::INVOICE]
        );
    }

    private function createDocumentBaseConfigDataTest(): void
    {
        $documentTypeId = $this->connection->fetchColumn(
            'SELECT `id` FROM `document_type` WHERE `technical_name` = :documentName',
            ['documentName' => InvoiceGenerator::INVOICE]
        );

        for ($i = 0; $i < 10; ++$i) {
            $config = null;

            if ($i % 2) {
                $config = [
                    'vatId' => 'XX 111 222 333',
                ];
            }

            if ($i % 3) {
                $config = [
                    'deliveryCountries' => [
                        Uuid::randomHex(),
                        Uuid::randomHex(),
                    ],
                ];
            }

            $this->getContainer()->get('document_base_config.repository')->create([[
                'id' => Uuid::randomHex(),
                'name' => 'test invoice',
                'config' => $config,
                'documentTypeId' => Uuid::fromBytesToHex($documentTypeId),
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]], Context::createDefaultContext());
        }
    }
}
