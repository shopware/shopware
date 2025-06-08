<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1718635021AddIntraCommunityLabelDocumentConfigToCreditNote;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1718635021AddIntraCommunityLabelDocumentConfigToCreditNote::class)]
class Migration1718635021AddIntraCommunityLabelDocumentConfigToCreditNoteTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testDisplayAdditionalNoteDeliverySettingIsNotSetByDefault(): void
    {
        $this->setDefaultStornoDocumentConfigValues();
        $this->executeMigration();

        $documentBaseConfig = $this->connection->fetchAssociative(
            <<<SQL
                SELECT * FROM document_base_config
                JOIN `document_type` ON `document_base_config`.`document_type_id` = `document_type`.`id`
                WHERE `document_type`.`technical_name` = :technicalName;
            SQL,
            ['technicalName' => 'credit_note'],
        );

        static::assertIsArray($documentBaseConfig);
        static::assertFalse(json_decode($documentBaseConfig['config'], true)['displayAdditionalNoteDelivery']);
        $expected = json_encode([
            'foo' => 'bar',
            'displayAdditionalNoteDelivery' => false,
        ], \JSON_THROW_ON_ERROR);

        static::assertJsonStringEqualsJsonString($expected, $documentBaseConfig['config']);
    }

    private function setDefaultStornoDocumentConfigValues(): void
    {
        $this->connection->fetchAssociative(
            <<<SQL
            UPDATE `document_base_config`
            SET `config` = :config
            WHERE `document_type_id` = (SELECT `id` FROM `document_type` WHERE `technical_name` = :technicalName);
            SQL,
            [
                'technicalName' => 'credit_note',
                'config' => '{"foo":"bar"}',
            ]
        );
    }

    private function executeMigration(): void
    {
        (new Migration1718635021AddIntraCommunityLabelDocumentConfigToCreditNote())->update($this->connection);
    }
}
