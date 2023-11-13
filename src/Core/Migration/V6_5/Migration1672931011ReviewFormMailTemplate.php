<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

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
class Migration1672931011ReviewFormMailTemplate extends MigrationStep
{
    use ImportTranslationsTrait;

    private const LOCALE_EN_GB = 'en-GB';
    private const LOCALE_DE_DE = 'de-DE';

    public function getCreationTimestamp(): int
    {
        return 1672931011;
    }

    public function update(Connection $connection): void
    {
        $technicalName = MailTemplateTypes::MAILTYPE_REVIEW_FORM;
        $typeId = Uuid::randomBytes();
        $templateId = Uuid::randomBytes();

        $existingTypeId = $this->getExistingMailTemplateTypeId($technicalName, $connection);
        if ($existingTypeId !== null) {
            $typeId = $existingTypeId;
        } else {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => $typeId,
                    'technical_name' => $technicalName,
                    'available_entities' => json_encode(['salesChannel' => 'sales_channel']),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $translations = new Translations(
                [
                    'mail_template_type_id' => $typeId,
                    'name' => 'Produktbewertung',
                ],
                [
                    'mail_template_type_id' => $typeId,
                    'name' => 'Product review',
                ]
            );

            $this->importTranslation('mail_template_type_translation', $translations, $connection);
        }

        $connection->insert(
            'mail_template',
            [
                'id' => $templateId,
                'mail_template_type_id' => $typeId,
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $translations = new Translations(
            [
                'mail_template_id' => $templateId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'Neue Produktbewertung',
                'content_html' => $this->getMailTemplateContent(self::LOCALE_DE_DE, true),
                'content_plain' => $this->getMailTemplateContent(self::LOCALE_DE_DE, false),
            ],
            [
                'mail_template_id' => $templateId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => 'New product review',
                'content_html' => $this->getMailTemplateContent(self::LOCALE_EN_GB, true),
                'content_plain' => $this->getMailTemplateContent(self::LOCALE_EN_GB, false),
            ],
        );

        $this->importTranslation('mail_template_translation', $translations, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getMailTemplateContent(string $locale, bool $html): string
    {
        $enHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/review_form/en-html.html.twig');
        $enPlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/review_form/en-plain.html.twig');
        $deHtml = \file_get_contents(__DIR__ . '/../Fixtures/mails/review_form/de-html.html.twig');
        $dePlain = \file_get_contents(__DIR__ . '/../Fixtures/mails/review_form/de-plain.html.twig');

        $templateContentMapping = [
            self::LOCALE_EN_GB => [
                'html' => $enHtml,
                'plain' => $enPlain,
            ],
            self::LOCALE_DE_DE => [
                'html' => $deHtml,
                'plain' => $dePlain,
            ],
        ];

        if (!\is_string($templateContentMapping[$locale][$html ? 'html' : 'plain'])) {
            throw new \RuntimeException(\sprintf('Could not MailTemplate data with locale %s', $locale));
        }

        return $templateContentMapping[$locale][$html ? 'html' : 'plain'];
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
