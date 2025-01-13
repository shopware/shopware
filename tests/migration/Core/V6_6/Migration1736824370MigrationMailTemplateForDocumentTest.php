<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;
use Shopware\Core\Migration\V6_6\Migration1736824370MigrationMailTemplateForDocument;

/**
 * @internal
 */
#[CoversClass(Migration1736824370MigrationMailTemplateForDocument::class)]
class Migration1736824370MigrationMailTemplateForDocumentTest extends TestCase
{
    use ImportTranslationsTrait;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $this->prepareData();

        $this->executeMigration();

        $documentTypeTranslationMapping = [
            MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE,
        ];

        foreach ($documentTypeTranslationMapping as $technicalName) {
            $mailTemplateId = $this->connection->fetchOne('
                SELECT `mail_template`.`id`
                FROM `mail_template`
                INNER JOIN `mail_template_type`
                    ON `mail_template`.`mail_template_type_id` = `mail_template_type`.`id`
                    AND `mail_template_type`.`technical_name` = :technicalName
           ', ['technicalName' => $technicalName]);

            if (!$mailTemplateId) {
                continue;
            }

            /** @var array{id: string, content_html: string, content_plain: string}|null $mailTemplate */
            $mailTemplate = $this->connection->fetchAssociative(
                '
                SELECT `mail_template`.`id`, `mail_template_translation`.`content_html`, `mail_template_translation`.`content_plain`
                FROM `mail_template`
                INNER JOIN `mail_template_translation`
                    ON `mail_template`.`id` = `mail_template_translation`.`mail_template_id`
                WHERE `mail_template`.`id` = :mailTemplateId',
                ['mailTemplateId' => $mailTemplateId],
            );

            static::assertNotNull($mailTemplate);

            if ($technicalName === MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE) {
                static::assertStringNotContainsString('{% if fileTypes', $mailTemplate['content_html']);
                static::assertStringNotContainsString('{% if fileTypes', $mailTemplate['content_plain']);
            } else {
                static::assertStringContainsString('{% if fileTypes', $mailTemplate['content_html']);
                static::assertStringContainsString('{% for type in fileTypes %}', $mailTemplate['content_html']);

                static::assertStringContainsString('{% if fileTypes', $mailTemplate['content_plain']);
                static::assertStringContainsString('{% for type in fileTypes %}', $mailTemplate['content_plain']);
            }
        }
    }

    private function executeMigration(): void
    {
        $migration = new Migration1736824370MigrationMailTemplateForDocument();
        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    private function prepareData(): void
    {
        $this->connection->executeStatement('
            UPDATE `mail_template`
            SET `updated_at` = :updatedAt
            WHERE `mail_template_type_id` IN (
                SELECT `id`
                FROM `mail_template_type`
                WHERE `technical_name` = :technicalName
            )
        ', [
            'updatedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'technicalName' => MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE,
        ]);

        $mailTemplateId = $this->connection->fetchOne('
            SELECT `mail_template`.`id`
            FROM `mail_template`
            INNER JOIN `mail_template_type`
                ON `mail_template`.`mail_template_type_id` = `mail_template_type`.`id`
                AND `mail_template_type`.`technical_name` = :technicalName
        ', ['technicalName' => MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE]);

        $translations = new Translations(
            [
                'mail_template_id' => $mailTemplateId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'Neues Dokument für Ihre Bestellung',
                'content_html' => 'html content',
                'content_plain' => 'plain content',
            ],
            [
                'mail_template_id' => $mailTemplateId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'New document for your order',
                'content_html' => 'html content',
                'content_plain' => 'plain content',
            ],
        );

        $this->importTranslation('mail_template_translation', $translations, $this->connection);
    }
}
