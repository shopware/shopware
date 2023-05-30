<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1657173907DownloadMailTemplate;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1657173907DownloadMailTemplate
 */
class Migration1657173907DownloadMailTemplateTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1657173907DownloadMailTemplate();
        $migration->update($this->connection);
        // test it can be executed multiple times
        $migration->update($this->connection);

        $this->assertMailTemplate();
    }

    public function testMigrationWithDifferentDefaultLanguage(): void
    {
        $nlLocale = $this->connection->fetchOne('SELECT id FROM locale WHERE code = :code', ['code' => 'nl-NL']);
        $this->connection->update('language', ['locale_id' => $nlLocale], ['hex(id)' => Defaults::LANGUAGE_SYSTEM]);

        $migration = new Migration1657173907DownloadMailTemplate();
        $migration->update($this->connection);
        // test it can be executed multiple times
        $migration->update($this->connection);

        $this->assertMailTemplate();
    }

    public function testMigrationWithDeletedGermanLanguage(): void
    {
        $this->connection->delete('language', [
            'name' => 'Deutsch',
        ]);

        $migration = new Migration1657173907DownloadMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `mail_template_type` WHERE `technical_name` = :type',
            ['type' => MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY]
        );

        static::assertIsString($mailTemplateTypeId);

        $mailTemplateTypeTranslationsCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :id',
            ['id' => Uuid::fromHexToBytes($mailTemplateTypeId)]
        );

        static::assertEquals(1, $mailTemplateTypeTranslationsCount);

        $mailTemplateId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `mail_template` WHERE `mail_template_type_id` = :id',
            ['id' => Uuid::fromHexToBytes($mailTemplateTypeId)]
        );

        static::assertIsString($mailTemplateId);

        $mailTemplateTranslationsCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template_translation` WHERE `mail_template_id` = :id',
            ['id' => Uuid::fromHexToBytes($mailTemplateId)]
        );

        static::assertEquals(1, $mailTemplateTranslationsCount);
    }

    private function assertMailTemplate(): void
    {
        $mailTemplateTypeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `mail_template_type` WHERE `technical_name` = :type',
            ['type' => MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY]
        );

        static::assertIsString($mailTemplateTypeId);

        $mailTemplateTypeTranslationsCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :id',
            ['id' => Uuid::fromHexToBytes($mailTemplateTypeId)]
        );

        static::assertEquals(2, $mailTemplateTypeTranslationsCount);

        $mailTemplateId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `mail_template` WHERE `mail_template_type_id` = :id',
            ['id' => Uuid::fromHexToBytes($mailTemplateTypeId)]
        );

        static::assertIsString($mailTemplateId);

        $mailTemplateTranslationsCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template_translation` WHERE `mail_template_id` = :id',
            ['id' => Uuid::fromHexToBytes($mailTemplateId)]
        );

        static::assertEquals(2, $mailTemplateTranslationsCount);
    }

    private function prepare(): void
    {
        $mailTemplateTypeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `mail_template_type` WHERE `technical_name` = :type',
            ['type' => MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY]
        );

        if (\is_string($mailTemplateTypeId)) {
            $this->connection->executeStatement(
                'DELETE FROM `mail_template` WHERE `mail_template_type_id` = :id',
                ['id' => Uuid::fromHexToBytes($mailTemplateTypeId)]
            );
            $this->connection->executeStatement(
                'DELETE FROM `mail_template_type` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($mailTemplateTypeId)]
            );
        }
    }
}
