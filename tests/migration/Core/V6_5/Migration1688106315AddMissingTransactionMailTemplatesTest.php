<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1688106315AddMissingTransactionMailTemplates;

/**
 * @internal
 */
#[CoversClass(Migration1688106315AddMissingTransactionMailTemplates::class)]
class Migration1688106315AddMissingTransactionMailTemplatesTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $templateTypeIds = $this->getTemplateTypeIds();

        foreach ($templateTypeIds as $id) {
            if (\is_string($id)) {
                $this->deleteExistingTemplate($id);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testMigration(): void
    {
        $migration = new Migration1688106315AddMissingTransactionMailTemplates();
        $migration->update($this->connection);
        $migration->update($this->connection);
        $this->assertMailTemplates('2');
    }

    /**
     * @throws Exception
     */
    public function testMigrationWithDifferentDefaultLanguage(): void
    {
        $nlLocale = $this->connection->fetchOne('SELECT id FROM locale WHERE code = :code', ['code' => 'nl-NL']);
        $this->connection->update('language', ['locale_id' => $nlLocale, 'name' => 'Nederlands'], ['hex(id)' => Defaults::LANGUAGE_SYSTEM]);

        $migration = new Migration1688106315AddMissingTransactionMailTemplates();
        $migration->update($this->connection);
        $migration->update($this->connection);
        $this->assertMailTemplates('2');
    }

    /**
     * @throws Exception
     */
    public function testMigrationWithDeletedLanguage(): void
    {
        $this->connection->delete('language', ['name' => 'Deutsch']);
        $migration = new Migration1688106315AddMissingTransactionMailTemplates();
        $migration->update($this->connection);
        $this->assertMailTemplates('1');
    }

    /**
     * @throws Exception
     */
    public function assertMailTemplates(string $expected): void
    {
        $assertTemplate = function (string $templateTypeId, string $expected): void {
            $mailTemplateTypeTranslationsCount = $this->connection->fetchOne('SELECT COUNT(*) FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :id', ['id' => Uuid::fromHexToBytes($templateTypeId)]);
            static::assertEquals($expected, $mailTemplateTypeTranslationsCount);

            $mailTemplateId = $this->connection->fetchOne('SELECT LOWER(HEX(`id`)) FROM `mail_template` WHERE `mail_template_type_id` = :id', ['id' => Uuid::fromHexToBytes($templateTypeId)]);
            static::assertIsString($mailTemplateId);

            $mailTemplateTranslationsCount = $this->connection->fetchOne('SELECT COUNT(*) FROM `mail_template_translation` WHERE `mail_template_id` = :id', ['id' => Uuid::fromHexToBytes($mailTemplateId)]);
            static::assertEquals($expected, $mailTemplateTranslationsCount);
        };

        $templateTypeIds = $this->getTemplateTypeIds();

        foreach ($templateTypeIds as $templateTypeId) {
            static::assertIsString($templateTypeId);

            $assertTemplate($templateTypeId, $expected);
        }
    }

    /**
     * @throws Exception
     */
    private function deleteExistingTemplate(string $templateTypeId): void
    {
        $this->connection->executeStatement('DELETE FROM `mail_template` WHERE `mail_template_type_id` = :id', ['id' => Uuid::fromHexToBytes($templateTypeId)]);
        $this->connection->executeStatement('DELETE FROM `mail_template_type` WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($templateTypeId)]);
    }

    /**
     * @throws Exception
     *
     * @return list<string|bool>
     */
    private function getTemplateTypeIds(): array
    {
        $getTemplateTypeId = function (string $technicalName): string|bool {
            return $this->connection->fetchOne('SELECT LOWER(HEX(`id`)) FROM `mail_template_type` WHERE `technical_name` = :type', ['type' => $technicalName]);
        };

        return [
            $getTemplateTypeId(Migration1688106315AddMissingTransactionMailTemplates::UNCONFIRMED_TYPE),
            $getTemplateTypeId(Migration1688106315AddMissingTransactionMailTemplates::CHARGEBACK_TYPE),
            $getTemplateTypeId(Migration1688106315AddMissingTransactionMailTemplates::AUTHORIZED_TYPE),
        ];
    }
}
