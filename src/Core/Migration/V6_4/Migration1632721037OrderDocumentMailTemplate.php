<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1632721037OrderDocumentMailTemplate extends MigrationStep
{
    private const LOCALE_EN_GB = 'en-GB';
    private const LOCALE_DE_DE = 'de-DE';

    public function getCreationTimestamp(): int
    {
        return 1632721037;
    }

    public function update(Connection $connection): void
    {
        $documentTypeTranslationMapping = [
            MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE => [
                'typeId' => Uuid::randomBytes(),
                'templateId' => Uuid::randomBytes(),
                'name' => 'Invoice',
                'nameDe' => 'Rechnung',
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE => [
                'typeId' => Uuid::randomBytes(),
                'templateId' => Uuid::randomBytes(),
                'name' => 'Delivery note',
                'nameDe' => 'Versandbenachrichtigung',
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE => [
                'typeId' => Uuid::randomBytes(),
                'templateId' => Uuid::randomBytes(),
                'name' => 'Credit note',
                'nameDe' => 'Gutschrift',
            ],
            MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE => [
                'typeId' => Uuid::randomBytes(),
                'templateId' => Uuid::randomBytes(),
                'name' => 'Cancellation invoice',
                'nameDe' => 'Stornorechnung',
            ],
        ];

        $defaultLangId = $this->getLanguageIdByLocale($connection, self::LOCALE_EN_GB);
        $deLangId = $this->getLanguageIdByLocale($connection, self::LOCALE_DE_DE);

        foreach ($documentTypeTranslationMapping as $technicalName => $values) {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => $values['typeId'],
                    'technical_name' => $technicalName,
                    'available_entities' => json_encode(['order' => 'order', 'salesChannel' => 'sales_channel']),
                    'template_data' => '{"order":{"orderNumber":"10060","orderCustomer":{"firstName":"Max","lastName":"Mustermann"}},"salesChannel":{"name":"Storefront"}}',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $connection->insert(
                'mail_template',
                [
                    'id' => $values['templateId'],
                    'mail_template_type_id' => $values['typeId'],
                    'system_default' => 1,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            if ($defaultLangId !== $deLangId) {
                $connection->insert(
                    'mail_template_type_translation',
                    [
                        'mail_template_type_id' => $values['typeId'],
                        'name' => $values['name'],
                        'language_id' => $defaultLangId,
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );

                $connection->insert(
                    'mail_template_translation',
                    [
                        'mail_template_id' => $values['templateId'],
                        'language_id' => $defaultLangId,
                        'sender_name' => '{{ salesChannel.name }}',
                        'subject' => 'New document for your order',
                        'description' => '',
                        'content_html' => $this->getMailTemplateContent($technicalName, self::LOCALE_EN_GB, true),
                        'content_plain' => $this->getMailTemplateContent($technicalName, self::LOCALE_EN_GB, false),
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $connection->insert(
                    'mail_template_type_translation',
                    [
                        'mail_template_type_id' => $values['typeId'],
                        'name' => $values['name'],
                        'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );

                $connection->insert(
                    'mail_template_translation',
                    [
                        'mail_template_id' => $values['templateId'],
                        'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                        'sender_name' => '{{ salesChannel.name }}',
                        'subject' => 'New document for your order',
                        'description' => '',
                        'content_html' => $this->getMailTemplateContent($technicalName, self::LOCALE_EN_GB, true),
                        'content_plain' => $this->getMailTemplateContent($technicalName, self::LOCALE_EN_GB, false),
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            }

            if ($deLangId) {
                $connection->insert(
                    'mail_template_type_translation',
                    [
                        'mail_template_type_id' => $values['typeId'],
                        'name' => $values['nameDe'],
                        'language_id' => $deLangId,
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );

                $connection->insert(
                    'mail_template_translation',
                    [
                        'mail_template_id' => $values['templateId'],
                        'language_id' => $deLangId,
                        'sender_name' => '{{ salesChannel.name }}',
                        'subject' => 'Neues Dokument für Ihre Bestellung',
                        'description' => '',
                        'content_html' => $this->getMailTemplateContent($technicalName, self::LOCALE_DE_DE, true),
                        'content_plain' => $this->getMailTemplateContent($technicalName, self::LOCALE_DE_DE, false),
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        /** @var string|false $languageId */
        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchColumn();
        if (!$languageId && $locale !== self::LOCALE_EN_GB) {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }

    private function getMailTemplateContent(string $technicalName, string $locale, bool $html): string
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

        $templateContentMapping = [
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

        if (!\is_string($templateContentMapping[$technicalName][$locale][$html ? 'html' : 'plain'])) {
            throw new \RuntimeException(\sprintf('Could not MailTemplate data for %s with locale %s', $technicalName, $locale));
        }

        return $templateContentMapping[$technicalName][$locale][$html ? 'html' : 'plain'];
    }
}
