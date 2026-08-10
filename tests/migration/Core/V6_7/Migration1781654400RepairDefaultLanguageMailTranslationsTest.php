<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1781654400RepairDefaultLanguageMailTranslations;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1781654400RepairDefaultLanguageMailTranslations::class)]
class Migration1781654400RepairDefaultLanguageMailTranslationsTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1781654400,
            (new Migration1781654400RepairDefaultLanguageMailTranslations())->getCreationTimestamp()
        );
    }

    public function testMigrationBackfillsMissingDefaultLanguageTranslation(): void
    {
        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        // Simulate a system whose default language uses a foreign locale, with en-GB and de-DE as
        // separate, non-default languages.
        $enGbLanguageId = $this->setUpForeignDefaultLanguage();
        $deLanguageId = $this->getLanguageByteId('de-DE');

        // Create a mail template + type that, like the buggy trait, only got en-GB and de-DE
        // translations but no translation for the system default language.
        $mailTemplateTypeId = Uuid::randomBytes();
        $this->connection->insert('mail_template_type', [
            'id' => $mailTemplateTypeId,
            'technical_name' => 'test_repair_' . Uuid::randomHex(),
            'available_entities' => json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $this->now(),
        ]);
        $this->insertTypeTranslation($mailTemplateTypeId, $enGbLanguageId, 'EN type name');
        $this->insertTypeTranslation($mailTemplateTypeId, $deLanguageId, 'DE type name');

        $mailTemplateId = Uuid::randomBytes();
        $this->connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => 1,
            'created_at' => $this->now(),
        ]);
        $this->insertTemplateTranslation($mailTemplateId, $enGbLanguageId, 'EN');
        $this->insertTemplateTranslation($mailTemplateId, $deLanguageId, 'DE');

        $migration = new Migration1781654400RepairDefaultLanguageMailTranslations();
        // Executed twice to ensure the migration is idempotent and does not create duplicates.
        $migration->update($this->connection);
        $migration->update($this->connection);

        // The system default language translation must now exist and carry the english content.
        $defaultType = $this->getTypeTranslation($mailTemplateTypeId, $defaultLanguageId);
        static::assertIsArray($defaultType);
        static::assertSame('EN type name', $defaultType['name']);

        $defaultTemplate = $this->getTemplateTranslation($mailTemplateId, $defaultLanguageId);
        static::assertIsArray($defaultTemplate);
        static::assertSame('EN subject', $defaultTemplate['subject']);
        static::assertSame('EN sender', $defaultTemplate['sender_name']);
        static::assertSame('<p>EN html</p>', $defaultTemplate['content_html']);
        static::assertSame('EN plain', $defaultTemplate['content_plain']);

        // The existing en-GB and de-DE translations stay untouched (no duplicates).
        static::assertCount(3, $this->getTypeTranslations($mailTemplateTypeId));
        static::assertCount(3, $this->getTemplateTranslations($mailTemplateId));
    }

    public function testMigrationLeavesExistingDefaultTranslationUntouched(): void
    {
        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $enGbLanguageId = $this->setUpForeignDefaultLanguage();

        $mailTemplateId = Uuid::randomBytes();
        $mailTemplateTypeId = Uuid::randomBytes();
        $this->connection->insert('mail_template_type', [
            'id' => $mailTemplateTypeId,
            'technical_name' => 'test_repair_' . Uuid::randomHex(),
            'available_entities' => json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $this->now(),
        ]);
        $this->connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => 1,
            'created_at' => $this->now(),
        ]);

        // The default language already has its own (non-english) content.
        $this->insertTypeTranslation($mailTemplateTypeId, $enGbLanguageId, 'EN type name');
        $this->insertTypeTranslation($mailTemplateTypeId, $defaultLanguageId, 'Default type name');
        $this->insertTemplateTranslation($mailTemplateId, $enGbLanguageId, 'EN');
        $this->insertTemplateTranslation($mailTemplateId, $defaultLanguageId, 'Default');

        $migration = new Migration1781654400RepairDefaultLanguageMailTranslations();
        $migration->update($this->connection);

        $defaultType = $this->getTypeTranslation($mailTemplateTypeId, $defaultLanguageId);
        static::assertIsArray($defaultType);
        static::assertSame('Default type name', $defaultType['name']);

        $defaultTemplate = $this->getTemplateTranslation($mailTemplateId, $defaultLanguageId);
        static::assertIsArray($defaultTemplate);
        static::assertSame('Default subject', $defaultTemplate['subject']);
    }

    /**
     * @return string the binary id of the separate en-GB language
     */
    private function setUpForeignDefaultLanguage(): string
    {
        $deLiLocaleId = $this->connection->fetchOne('SELECT `id` FROM `locale` WHERE `code` = :code', ['code' => 'de-LI']);
        static::assertIsString($deLiLocaleId);
        $enGbLocaleId = $this->connection->fetchOne('SELECT `id` FROM `locale` WHERE `code` = :code', ['code' => 'en-GB']);
        static::assertIsString($enGbLocaleId);

        $this->connection->update(
            'language',
            ['name' => 'ForeignLang', 'locale_id' => $deLiLocaleId, 'translation_code_id' => $deLiLocaleId],
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $enGbLanguageId = Uuid::randomBytes();
        $this->connection->insert('language', [
            'id' => $enGbLanguageId,
            'name' => 'English',
            'locale_id' => $enGbLocaleId,
            'translation_code_id' => $enGbLocaleId,
            'created_at' => $this->now(),
        ]);

        return $enGbLanguageId;
    }

    private function insertTypeTranslation(string $mailTemplateTypeId, string $languageId, string $name): void
    {
        $this->connection->insert('mail_template_type_translation', [
            'mail_template_type_id' => $mailTemplateTypeId,
            'language_id' => $languageId,
            'name' => $name,
            'created_at' => $this->now(),
        ]);
    }

    private function insertTemplateTranslation(string $mailTemplateId, string $languageId, string $prefix): void
    {
        $this->connection->insert('mail_template_translation', [
            'mail_template_id' => $mailTemplateId,
            'language_id' => $languageId,
            'sender_name' => $prefix . ' sender',
            'subject' => $prefix . ' subject',
            'description' => $prefix . ' description',
            'content_html' => '<p>' . $prefix . ' html</p>',
            'content_plain' => $prefix . ' plain',
            'created_at' => $this->now(),
        ]);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getTypeTranslation(string $mailTemplateTypeId, string $languageId): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :id AND `language_id` = :languageId',
            ['id' => $mailTemplateTypeId, 'languageId' => $languageId]
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getTemplateTranslation(string $mailTemplateId, string $languageId): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM `mail_template_translation` WHERE `mail_template_id` = :id AND `language_id` = :languageId',
            ['id' => $mailTemplateId, 'languageId' => $languageId]
        );
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getTypeTranslations(string $mailTemplateTypeId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :id',
            ['id' => $mailTemplateTypeId]
        );
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getTemplateTranslations(string $mailTemplateId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM `mail_template_translation` WHERE `mail_template_id` = :id',
            ['id' => $mailTemplateId]
        );
    }

    private function getLanguageByteId(string $locale): string
    {
        $languageByteId = $this->connection->fetchOne(
            'SELECT `language`.`id`
             FROM `language`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
             WHERE `locale`.`code` = :code',
            ['code' => $locale]
        );
        static::assertIsString($languageByteId);

        return $languageByteId;
    }

    private function now(): string
    {
        return (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
