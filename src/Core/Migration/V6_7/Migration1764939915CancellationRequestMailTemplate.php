<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\MailUpdate as MailStruct;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1764939915CancellationRequestMailTemplate extends MigrationStep
{
    public const MAIL_TEMPLATE_TYPE_TRANSLATIONS = [
        'en_name' => 'Cancellation request',
        'de_name' => 'Kündigungsantrag',
    ];

    public const MAIL_TEMPLATE_TRANSLATIONS = [
        'en_subject' => 'Cancellation request received',
        'de_subject' => 'Kündigungsantrag erhalten',
        'en_description' => '',
        'de_description' => '',
    ];

    public function getCreationTimestamp(): int
    {
        return 1764939915;
    }

    public function update(Connection $connection): void
    {
        $enLanguage_byteId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLanguage_byteId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $mailTemplateType_bytesId = $this->createMailTemplateType($connection, $enLanguage_byteId, $deLanguage_byteId);
        $this->createMailTemplate($connection, $mailTemplateType_bytesId, $enLanguage_byteId, $deLanguage_byteId);
    }

    private function createMailTemplate(
        Connection $connection,
        string $mailTemplateType_bytesId,
        string $enLanguage_byteId,
        string $deLanguage_byteId
    ): void {
        $mailStruct = $this->createMailStruct();
        $mailTemplate_byteId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $mailTemplate_byteId,
                'mail_template_type_id' => $mailTemplateType_bytesId,
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $mailTemplate_byteId,
                'language_id' => $enLanguage_byteId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => self::MAIL_TEMPLATE_TRANSLATIONS['en_subject'],
                'description' => self::MAIL_TEMPLATE_TRANSLATIONS['en_description'],
                'content_html' => $mailStruct->getEnHtml(),
                'content_plain' => $mailStruct->getEnPlain(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $mailTemplate_byteId,
                'language_id' => $deLanguage_byteId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => self::MAIL_TEMPLATE_TRANSLATIONS['de_subject'],
                'description' => self::MAIL_TEMPLATE_TRANSLATIONS['de_description'],
                'content_html' => $mailStruct->getDeHtml(),
                'content_plain' => $mailStruct->getDePlain(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function createMailStruct(): MailStruct
    {
        $filesystem = new Filesystem();

        $mailStruct = new MailStruct(MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST);
        $mailStruct->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_request_merchant/en-html.html.twig'));
        $mailStruct->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_request_merchant/en-plain.text.twig'));
        $mailStruct->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_request_merchant/de-html.html.twig'));
        $mailStruct->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_request_merchant/de-plain.text.twig'));
    }

    private function createMailTemplateType(Connection $connection, string $enLanguage_byteId, string $deLanguage_byteId): string
    {
        $mailTemplateType_byteId = Uuid::randomBytes();

        $mailTemplateType = [
            'id' => $mailTemplateType_byteId,
            'technical_name' => MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST,
            'available_entities' => json_encode([]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('mail_template_type', $mailTemplateType);

        $connection->insert(
            'mail_template_type_translation',
            [
                'mail_template_type_id' => $mailTemplateType_byteId,
                'name' => self::MAIL_TEMPLATE_TYPE_TRANSLATIONS['en_name'],
                'language_id' => $enLanguage_byteId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_type_translation',
            [
                'mail_template_type_id' => $mailTemplateType_byteId,
                'name' => self::MAIL_TEMPLATE_TYPE_TRANSLATIONS['de_name'],
                'language_id' => $deLanguage_byteId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        return $mailTemplateType_byteId;
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }
}
