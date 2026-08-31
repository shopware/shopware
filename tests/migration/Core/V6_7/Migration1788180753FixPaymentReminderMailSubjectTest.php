<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1788180753FixPaymentReminderMailSubject;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1788180753FixPaymentReminderMailSubject::class)]
class Migration1788180753FixPaymentReminderMailSubjectTest extends MailTemplateMigrationTestCase
{
    public function testMigrationUpdatesPaymentReminderMailSubject(): void
    {
        $this->prepareDefaultMailTemplateSubject('New document for your order', 'Neues Dokument für Ihre Bestellung');

        $migration = new Migration1788180753FixPaymentReminderMailSubject();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $subjects = $this->getPaymentReminderMailTemplateSubjects();

        static::assertSame('Payment reminder for your order with {{ salesChannel.name }}', $subjects['en']);
        static::assertSame('Zahlungserinnerung für Ihre Bestellung bei {{ salesChannel.name }}', $subjects['de']);
    }

    public function testMigrationDoesNotOverwriteCustomizedPaymentReminderMailSubject(): void
    {
        $this->prepareCustomizedMailTemplateSubject('Custom EN subject', 'Individueller DE Betreff');

        (new Migration1788180753FixPaymentReminderMailSubject())->update($this->connection);

        $subjects = $this->getPaymentReminderMailTemplateSubjects();

        static::assertSame('Custom EN subject', $subjects['en']);
        static::assertSame('Individueller DE Betreff', $subjects['de']);
    }

    private function prepareDefaultMailTemplateSubject(string $enSubject, string $deSubject): void
    {
        $this->prepareMailTemplateSubject($enSubject, $deSubject, null, null);
    }

    private function prepareCustomizedMailTemplateSubject(string $enSubject, string $deSubject): void
    {
        $updatedAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->prepareMailTemplateSubject($enSubject, $deSubject, $updatedAt, $updatedAt);
    }

    private function prepareMailTemplateSubject(
        string $enSubject,
        string $deSubject,
        ?string $templateUpdatedAt,
        ?string $translationUpdatedAt
    ): void {
        $mailTemplateTypeId = $this->getMailTemplateTypeId(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED);
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        $this->connection->executeStatement(
            'UPDATE `mail_template` SET `updated_at` = :updatedAt WHERE `id` = :id',
            [
                'id' => $mailTemplateId,
                'updatedAt' => $templateUpdatedAt,
            ],
        );

        $this->connection->executeStatement(
            '
            UPDATE `mail_template_translation`
            SET `subject` = CASE
                    WHEN `language_id` = :enLanguageId THEN :enSubject
                    WHEN `language_id` = :deLanguageId THEN :deSubject
                    ELSE `subject`
                END,
                `updated_at` = :updatedAt
            WHERE `mail_template_id` = :mailTemplateId
            ',
            [
                'mailTemplateId' => $mailTemplateId,
                'enLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'enSubject' => $enSubject,
                'deLanguageId' => Uuid::fromHexToBytes($this->getLanguageId('de-DE')),
                'deSubject' => $deSubject,
                'updatedAt' => $translationUpdatedAt,
            ],
        );
    }

    /**
     * @return array{en: string, de: string}
     */
    private function getPaymentReminderMailTemplateSubjects(): array
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED);
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        $subjects = $this->connection->fetchAllKeyValue(
            '
            SELECT LOWER(HEX(`language_id`)), `subject`
            FROM `mail_template_translation`
            WHERE `mail_template_id` = :mailTemplateId
                AND `language_id` IN (:enLanguageId, :deLanguageId)
            ',
            [
                'mailTemplateId' => $mailTemplateId,
                'enLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'deLanguageId' => Uuid::fromHexToBytes($this->getLanguageId('de-DE')),
            ],
        );

        return [
            'en' => $subjects[Defaults::LANGUAGE_SYSTEM],
            'de' => $subjects[$this->getLanguageId('de-DE')],
        ];
    }

    private function getLanguageId(string $localeCode): string
    {
        $languageId = $this->connection->fetchOne(
            '
            SELECT LOWER(HEX(`language`.`id`))
            FROM `language`
            INNER JOIN `locale`
                ON `language`.`locale_id` = `locale`.`id`
                    AND `locale`.`code` = :localeCode
            ',
            ['localeCode' => $localeCode],
        );

        static::assertIsString($languageId);

        return $languageId;
    }
}
