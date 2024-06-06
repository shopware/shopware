<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1717601705AddIntraCommunityLabelDocumentConfigToStorno;

/**
 * @internal
 */
#[CoversClass(Migration1717601705AddIntraCommunityLabelDocumentConfigToStorno::class)]
class Migration1717601705AddIntraCommunityLabelDocumentConfigToStornoTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
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
            ['technicalName' => 'storno']
        );

        static::assertFalse(json_decode($documentBaseConfig['config'], true)['displayAdditionalNoteDelivery']);
        $expected = [
            'foo' => 'bar',
            'displayAdditionalNoteDelivery' => false,
        ];

        static::assertJsonStringEqualsJsonString(json_encode($expected), $documentBaseConfig['config']);
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
                'technicalName' => 'storno',
                'config' => '{"foo":"bar"}',
            ]
        );
    }

    private function executeMigration(): void
    {
        (new Migration1717601705AddIntraCommunityLabelDocumentConfigToStorno())->update($this->connection);
    }
}
