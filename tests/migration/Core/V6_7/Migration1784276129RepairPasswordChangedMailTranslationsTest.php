<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1784276129RepairPasswordChangedMailTranslations;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1784276129RepairPasswordChangedMailTranslations::class)]
class Migration1784276129RepairPasswordChangedMailTranslationsTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1784276129, (new Migration1784276129RepairPasswordChangedMailTranslations())->getCreationTimestamp());
    }

    public function testUpdateRepairsDenglishGermanTranslations(): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        $deLanguageId = $this->fetchLanguageId($this->connection, 'de-DE');
        static::assertIsString($deLanguageId);

        $this->connection->executeStatement(
            'UPDATE `mail_template_type_translation` SET `name` = :name WHERE `mail_template_type_id` = :typeId AND `language_id` = :languageId',
            ['name' => 'Kunden-Password geändert', 'typeId' => $mailTemplateTypeId, 'languageId' => $deLanguageId],
        );
        $this->connection->executeStatement(
            'UPDATE `mail_template_translation` SET `subject` = :subject WHERE `language_id` = :languageId AND `mail_template_id` IN (
                SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId
            )',
            ['subject' => 'Kunden-Password geändert', 'typeId' => $mailTemplateTypeId, 'languageId' => $deLanguageId],
        );

        $migration = new Migration1784276129RepairPasswordChangedMailTranslations();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame('Kunden-Passwort geändert', $this->fetchTypeName($mailTemplateTypeId, $deLanguageId));
        static::assertSame('Kunden-Passwort geändert', $this->fetchSubject($mailTemplateTypeId, $deLanguageId));
    }

    public function testUpdateKeepsCustomizedGermanTranslations(): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        $deLanguageId = $this->fetchLanguageId($this->connection, 'de-DE');
        static::assertIsString($deLanguageId);

        $this->connection->executeStatement(
            'UPDATE `mail_template_type_translation` SET `name` = :name WHERE `mail_template_type_id` = :typeId AND `language_id` = :languageId',
            ['name' => 'Passwort-Info', 'typeId' => $mailTemplateTypeId, 'languageId' => $deLanguageId],
        );
        $this->connection->executeStatement(
            'UPDATE `mail_template_translation` SET `subject` = :subject WHERE `language_id` = :languageId AND `mail_template_id` IN (
                SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId
            )',
            ['subject' => 'Ihr Passwort wurde geändert', 'typeId' => $mailTemplateTypeId, 'languageId' => $deLanguageId],
        );

        (new Migration1784276129RepairPasswordChangedMailTranslations())->update($this->connection);

        static::assertSame('Passwort-Info', $this->fetchTypeName($mailTemplateTypeId, $deLanguageId));
        static::assertSame('Ihr Passwort wurde geändert', $this->fetchSubject($mailTemplateTypeId, $deLanguageId));
    }

    public function testUpdateRepairsCopiedTranslationsOfAnyLanguage(): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        $languageId = $this->createLanguage('nl-NL');
        $this->seedBrokenTranslationsForLanguage($mailTemplateTypeId, $languageId);

        (new Migration1784276129RepairPasswordChangedMailTranslations())->update($this->connection);

        static::assertSame('Kunden-Passwort geändert', $this->fetchTypeName($mailTemplateTypeId, $languageId));
        static::assertSame('Kunden-Passwort geändert', $this->fetchSubject($mailTemplateTypeId, $languageId));
    }

    private function seedBrokenTranslationsForLanguage(string $mailTemplateTypeId, string $languageId): void
    {
        $this->connection->insert('mail_template_type_translation', [
            'mail_template_type_id' => $mailTemplateTypeId,
            'language_id' => $languageId,
            'name' => 'Kunden-Password geändert',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $mailTemplateId = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId',
            ['typeId' => $mailTemplateTypeId],
        );
        static::assertIsString($mailTemplateId);

        $this->connection->insert('mail_template_translation', [
            'mail_template_id' => $mailTemplateId,
            'language_id' => $languageId,
            'subject' => 'Kunden-Password geändert',
            'sender_name' => '{{ salesChannel.name }}',
            'content_html' => '',
            'content_plain' => '',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createLanguage(string $localeCode): string
    {
        $languageId = Uuid::randomBytes();

        $this->connection->insert('language', [
            'id' => $languageId,
            'name' => $localeCode,
            'locale_id' => $this->fetchLocaleId($localeCode),
            'translation_code_id' => $this->fetchLocaleId($localeCode),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $languageId;
    }

    private function fetchLocaleId(string $code): string
    {
        $localeId = $this->connection->fetchOne('SELECT `id` FROM `locale` WHERE `code` = :code LIMIT 1', ['code' => $code]);
        static::assertIsString($localeId);

        return $localeId;
    }

    private function getMailTemplateTypeId(): string
    {
        $mailTemplateTypeId = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => CustomerPasswordChangedEvent::EVENT_NAME],
        );
        static::assertIsString($mailTemplateTypeId);

        return $mailTemplateTypeId;
    }

    private function fetchTypeName(string $mailTemplateTypeId, string $languageId): string
    {
        $name = $this->connection->fetchOne(
            'SELECT `name` FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :typeId AND `language_id` = :languageId',
            ['typeId' => $mailTemplateTypeId, 'languageId' => $languageId],
        );
        static::assertIsString($name);

        return $name;
    }

    private function fetchSubject(string $mailTemplateTypeId, string $languageId): string
    {
        $subject = $this->connection->fetchOne(
            'SELECT `subject` FROM `mail_template_translation` WHERE `language_id` = :languageId AND `mail_template_id` IN (
                SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId
            )',
            ['typeId' => $mailTemplateTypeId, 'languageId' => $languageId],
        );
        static::assertIsString($subject);

        return $subject;
    }
}
