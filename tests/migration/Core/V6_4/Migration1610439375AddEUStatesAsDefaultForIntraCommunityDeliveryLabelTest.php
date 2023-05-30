<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1610439375AddEUStatesAsDefaultForIntraCommunityDeliveryLabel;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1610439375AddEUStatesAsDefaultForIntraCommunityDeliveryLabel
 */
class Migration1610439375AddEUStatesAsDefaultForIntraCommunityDeliveryLabelTest extends TestCase
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
        $this->createDocumentBaseConfigDataTest();

        $listInvoiceData = $this->getListInvoiceData();
        foreach ($listInvoiceData as $invoiceData) {
            $invoiceConfig = json_decode($invoiceData['config'], true, 512, \JSON_THROW_ON_ERROR);
            unset($invoiceConfig['deliveryCountries']);

            $this->connection->executeStatement(
                'UPDATE `document_base_config` SET `config` = :invoiceData WHERE `id` = :documentConfigId',
                [
                    'invoiceData' => json_encode($invoiceConfig, \JSON_THROW_ON_ERROR),
                    'documentConfigId' => $invoiceData['id'],
                ]
            );
            static::assertFalse(isset($invoiceConfig['deliveryCountries']));
        }
        $migration = new Migration1610439375AddEUStatesAsDefaultForIntraCommunityDeliveryLabel();
        $migration->update($this->connection);

        $listInvoiceData = $this->getListInvoiceData();

        $euStates = $this->connection->executeQuery(
            'SELECT `id` FROM `country` WHERE `iso`
                IN (\'AT\', \'BE\', \'BG\', \'CY\', \'CZ\', \'DE\', \'DK\', \'EE\', \'GR\', \'ES\', \'FI\', \'FR\', \'GB\', \'HU\', \'IE\', \'IT\',
                \'LT\', \'LU\', \'LV\', \'MT\', \'NL\', \'PL\', \'PT\', \'RO\', \'SE\', \'SI\', \'SK\', \'HR\')'
        )->fetchFirstColumn();

        foreach ($listInvoiceData as $invoiceData) {
            $invoiceConfig = json_decode($invoiceData['config'], true, 512, \JSON_THROW_ON_ERROR);

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
            $invoiceConfig = json_decode($invoiceData['config'], true, 512, \JSON_THROW_ON_ERROR);
            unset($invoiceConfig['deliveryCountries']);

            $this->connection->executeStatement(
                'UPDATE `document_base_config` SET `config` = :invoiceData WHERE `id` = :documentConfigId',
                [
                    'invoiceData' => json_encode($invoiceConfig, \JSON_THROW_ON_ERROR),
                    'documentConfigId' => $invoiceData['id'],
                ]
            );
        }
    }

    /**
     * @return array{id: string, config: string}[]
     */
    private function getListInvoiceData(): array
    {
        /** @var array{id: string, config: string}[] $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT `document_base_config`.`id`, `document_base_config`.`config` FROM `document_base_config`
            LEFT JOIN `document_type` ON `document_base_config`.`document_type_id` = `document_type`.`id`
            WHERE `document_type`.`technical_name` = :documentName',
            ['documentName' => InvoiceRenderer::TYPE]
        );

        return $result;
    }

    private function createDocumentBaseConfigDataTest(): void
    {
        $documentTypeId = $this->connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE `technical_name` = :documentName',
            ['documentName' => InvoiceRenderer::TYPE]
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

            $this->connection->insert(
                'document_base_config',
                [
                    'id' => Uuid::randomBytes(),
                    'name' => 'test invoice',
                    'document_type_id' => $documentTypeId,
                    'config' => json_encode($config, \JSON_THROW_ON_ERROR),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }
}
