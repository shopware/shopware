<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1736831335AddGenerateDocumentTypesForDocumentConfig;

/**
 * @internal
 */
#[CoversClass(Migration1736831335AddGenerateDocumentTypesForDocumentConfig::class)]
class Migration1736831335AddGenerateDocumentTypesForDocumentConfigTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $this->setDefaultDocumentConfigValues();
        $this->executeMigration();

        $documentBaseConfig = $this->connection->fetchAssociative(
            <<<SQL
                SELECT * FROM document_base_config
                JOIN `document_type` ON `document_base_config`.`document_type_id` = `document_type`.`id`
                WHERE `document_type`.`technical_name` = :technicalName;
            SQL,
            ['technicalName' => 'invoice'],
        );

        static::assertIsArray($documentBaseConfig);
        $expected = json_encode([
            'foo' => 'bar',
            'fileTypes' => [HtmlRenderer::FILE_EXTENSION, PdfRenderer::FILE_EXTENSION],
        ], \JSON_THROW_ON_ERROR);

        static::assertJsonStringEqualsJsonString($expected, $documentBaseConfig['config']);
    }

    private function setDefaultDocumentConfigValues(): void
    {
        $this->connection->fetchAssociative(
            <<<SQL
            UPDATE `document_base_config`
            SET `config` = :config
            WHERE `document_type_id` = (SELECT `id` FROM `document_type` WHERE `technical_name` = :technicalName);
            SQL,
            [
                'technicalName' => 'invoice',
                'config' => '{"foo":"bar"}',
            ]
        );
    }

    private function executeMigration(): void
    {
        $migration = new Migration1736831335AddGenerateDocumentTypesForDocumentConfig();
        $migration->update($this->connection);
        $migration->update($this->connection);
    }
}
