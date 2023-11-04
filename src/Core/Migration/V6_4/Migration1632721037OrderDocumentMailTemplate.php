<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('core')]
class Migration1632721037OrderDocumentMailTemplate extends MigrationStep
{
    use ImportTranslationsTrait;

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

        foreach ($documentTypeTranslationMapping as $technicalName => $values) {
            $existingTypeId = $this->getExistingMailTemplateTypeId($technicalName, $connection);
            if ($existingTypeId !== null) {
                $values['typeId'] = $existingTypeId;
            } else {
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

                $translations = new Translations(
                    [
                        'mail_template_type_id' => $values['typeId'],
                        'name' => $values['nameDe'],
                    ],
                    [
                        'mail_template_type_id' => $values['typeId'],
                        'name' => $values['name'],
                    ]
                );

                $this->importTranslation('mail_template_type_translation', $translations, $connection);
            }

            $connection->insert(
                'mail_template',
                [
                    'id' => $values['templateId'],
                    'mail_template_type_id' => $values['typeId'],
                    'system_default' => 1,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $translations = new Translations(
                [
                    'mail_template_id' => $values['templateId'],
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => 'Neues Dokument für Ihre Bestellung',
                    'content_html' => $this->getMailTemplateContent($technicalName, self::LOCALE_DE_DE, true),
                    'content_plain' => $this->getMailTemplateContent($technicalName, self::LOCALE_DE_DE, false),
                ],
                [
                    'mail_template_id' => $values['templateId'],
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => 'New document for your order',
                    'content_html' => $this->getMailTemplateContent($technicalName, self::LOCALE_DE_DE, true),
                    'content_plain' => $this->getMailTemplateContent($technicalName, self::LOCALE_DE_DE, false),
                ],
            );

            $this->importTranslation('mail_template_translation', $translations, $connection);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
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

    private function getExistingMailTemplateTypeId(string $technicalName, Connection $connection): ?string
    {
        $result = $connection->createQueryBuilder()
            ->select('id')
            ->from('mail_template_type')
            ->where('technical_name = :technicalName')
            ->setParameter('technicalName', $technicalName)
            ->executeQuery()
            ->fetchOne();

        return $result ?: null;
    }
}
