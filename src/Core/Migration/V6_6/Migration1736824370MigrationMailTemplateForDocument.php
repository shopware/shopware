<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1736824370MigrationMailTemplateForDocument extends MigrationStep
{
    use ImportTranslationsTrait;

    private const LOCALE_EN_GB = 'en-GB';
    private const LOCALE_DE_DE = 'de-DE';

    public function getCreationTimestamp(): int
    {
        return 1736824370;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $documentTypeTranslationMapping = [
            MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE,
            MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE,
        ];

        $templateMapping = $this->getTemplateMapping();

        foreach ($documentTypeTranslationMapping as $technicalName) {
            $mailTemplateId = $connection->fetchOne('
                SELECT `mail_template`.`id`
                FROM `mail_template`
                INNER JOIN `mail_template_type`
                    ON `mail_template`.`mail_template_type_id` = `mail_template_type`.`id`
                    AND `mail_template_type`.`technical_name` = :technicalName
                WHERE `mail_template`.`updated_at` IS NULL
           ', ['technicalName' => $technicalName]);

            if (!$mailTemplateId) {
                continue;
            }

            $translations = new Translations(
                [
                    'mail_template_id' => $mailTemplateId,
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => 'Neues Dokument für Ihre Bestellung',
                    'content_html' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_DE_DE, true),
                    'content_plain' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_DE_DE, false),
                ],
                [
                    'mail_template_id' => $mailTemplateId,
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => 'New document for your order',
                    'content_html' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_EN_GB, true),
                    'content_plain' => $this->getMailTemplateContent($templateMapping, $technicalName, self::LOCALE_EN_GB, false),
                ],
            );

            $this->importTranslation('mail_template_translation', $translations, $connection);
        }
    }

    /**
     * @return array<string, array<string, array<string, string|false>>>
     */
    private function getTemplateMapping(): array
    {
        $invoiceEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/invoice_mail/en-html.html.twig');
        $invoiceEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/invoice_mail/en-plain.html.twig');
        $invoiceDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/invoice_mail/de-html.html.twig');
        $invoiceDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/invoice_mail/de-plain.html.twig');
        $deliveryNoteEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/delivery_mail/en-html.html.twig');
        $deliveryNoteEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/delivery_mail/en-plain.html.twig');
        $deliveryNoteDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/delivery_mail/de-html.html.twig');
        $deliveryNoteDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/delivery_mail/de-plain.html.twig');
        $creditNoteEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/credit_note_mail/en-html.html.twig');
        $creditNoteEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/credit_note_mail/en-plain.html.twig');
        $creditNoteDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/credit_note_mail/de-html.html.twig');
        $creditNoteDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/credit_note_mail/de-plain.html.twig');
        $cancellationInvoiceEnHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/cancellation_mail/en-html.html.twig');
        $cancellationInvoiceEnPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/cancellation_mail/en-plain.html.twig');
        $cancellationInvoiceDeHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/cancellation_mail/de-html.html.twig');
        $cancellationInvoiceDePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/cancellation_mail/de-plain.html.twig');

        return [
            MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE => [
                self::LOCALE_EN_GB => [
                    'html' => $invoiceEnHtml,
                    'plain' => $invoiceEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $invoiceDeHtml,
                    'plain' => $invoiceDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE => [
                self::LOCALE_EN_GB => [
                    'html' => $deliveryNoteEnHtml,
                    'plain' => $deliveryNoteEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $deliveryNoteDeHtml,
                    'plain' => $deliveryNoteDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE => [
                self::LOCALE_EN_GB => [
                    'html' => $creditNoteEnHtml,
                    'plain' => $creditNoteEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $creditNoteDeHtml,
                    'plain' => $creditNoteDePlain,
                ],
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE => [
                self::LOCALE_EN_GB => [
                    'html' => $cancellationInvoiceEnHtml,
                    'plain' => $cancellationInvoiceEnPlain,
                ],
                self::LOCALE_DE_DE => [
                    'html' => $cancellationInvoiceDeHtml,
                    'plain' => $cancellationInvoiceDePlain,
                ],
            ],
        ];
    }

    /**
     * @param array<string, array<string, array<string, string|false>>> $templateMapping
     */
    private function getMailTemplateContent(array $templateMapping, string $technicalName, string $locale, bool $html): string
    {
        if (!\is_string($templateMapping[$technicalName][$locale][$html ? 'html' : 'plain'])) {
            return '';
        }

        return $templateMapping[$technicalName][$locale][$html ? 'html' : 'plain'];
    }
}
