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
    public const MERCHANT_DIRECTORY = 'cancellation_request.merchant';

    public const MAIL_TEMPLATE_TYPE_TRANSLATIONS = [
        'en_name' => 'Cancellation request',
        'de_name' => 'Widerrufsantrag',
    ];

    public const MAIL_TEMPLATE_TRANSLATIONS_MERCHANT = [
        'en_subject' => 'Cancellation request received',
        'de_subject' => 'Widerrufsantrag erhalten',
        'en_description' => 'Received cancellation request from customer',
        'de_description' => 'Widerrufsantrag von Kunden erhalten',
    ];

    public function getCreationTimestamp(): int
    {
        return 1764939915;
    }

    public function update(Connection $connection): void
    {
        $enLanguageByteId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLanguageByteId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $mailTemplateTypeByteId = $this->createMailTemplateType($connection, $enLanguageByteId, $deLanguageByteId);
        $this->createMailTemplateMerchant($connection, $mailTemplateTypeByteId, $enLanguageByteId, $deLanguageByteId);
    }

    private function createMailTemplateMerchant(
        Connection $connection,
        string $mailTemplateTypeByteId,
        ?string $enLanguageByteId,
        ?string $deLanguageByteId
    ): void {
        $hasMailTemplate = true;
        $mailStruct = $this->createMailStruct(self::MERCHANT_DIRECTORY);
        $mailTemplateByteId = $this->getMailTemplateId($connection, $mailTemplateTypeByteId);
        if (empty($mailTemplateByteId)) {
            $mailTemplateByteId = Uuid::randomBytes();
            $hasMailTemplate = false;
        }

        if (!$hasMailTemplate) {
            $connection->insert(
                'mail_template',
                [
                    'id' => $mailTemplateByteId,
                    'mail_template_type_id' => $mailTemplateTypeByteId,
                    'system_default' => 1,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if (\is_string($enLanguageByteId) && !$this->hasMailTemplateTranslation($connection, $mailTemplateByteId, $enLanguageByteId)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailTemplateByteId,
                    'language_id' => $enLanguageByteId,
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => self::MAIL_TEMPLATE_TRANSLATIONS_MERCHANT['en_subject'],
                    'description' => self::MAIL_TEMPLATE_TRANSLATIONS_MERCHANT['en_description'],
                    'content_html' => $mailStruct->getEnHtml(),
                    'content_plain' => $mailStruct->getEnPlain(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if (\is_string($deLanguageByteId) && !$this->hasMailTemplateTranslation($connection, $mailTemplateByteId, $deLanguageByteId)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailTemplateByteId,
                    'language_id' => $deLanguageByteId,
                    'sender_name' => '{{ salesChannel.name }}',
                    'subject' => self::MAIL_TEMPLATE_TRANSLATIONS_MERCHANT['de_subject'],
                    'description' => self::MAIL_TEMPLATE_TRANSLATIONS_MERCHANT['de_description'],
                    'content_html' => $mailStruct->getDeHtml(),
                    'content_plain' => $mailStruct->getDePlain(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function createMailStruct(string $directory): MailStruct
    {
        $filesystem = new Filesystem();

        $mailStruct = new MailStruct(MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST_MERCHANT);
        $mailStruct->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $directory . '/en-html.html.twig'));
        $mailStruct->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $directory . '/en-plain.txt.twig'));
        $mailStruct->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $directory . '/de-html.html.twig'));
        $mailStruct->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $directory . '/de-plain.txt.twig'));

        return $mailStruct;
    }

    private function createMailTemplateType(Connection $connection, ?string $enLanguageByteId, ?string $deLanguageByteId): string
    {
        $hasMailTemplateType = true;
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection);
        if (empty($mailTemplateTypeByteId)) {
            $mailTemplateTypeByteId = Uuid::randomBytes();
            $hasMailTemplateType = false;
        }

        if (!$hasMailTemplateType) {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => $mailTemplateTypeByteId,
                    'technical_name' => MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST_MERCHANT,
                    'available_entities' => \json_encode([], \JSON_THROW_ON_ERROR),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if (\is_string($enLanguageByteId) && !$this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $enLanguageByteId)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailTemplateTypeByteId,
                    'name' => self::MAIL_TEMPLATE_TYPE_TRANSLATIONS['en_name'],
                    'language_id' => $enLanguageByteId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if (\is_string($deLanguageByteId) && !$this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $deLanguageByteId)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailTemplateTypeByteId,
                    'name' => self::MAIL_TEMPLATE_TYPE_TRANSLATIONS['de_name'],
                    'language_id' => $deLanguageByteId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        return $mailTemplateTypeByteId;
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

    private function getMailTemplateTypeId(Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST_MERCHANT]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    private function hasTemplateTypeTranslation(Connection $connection, string $mailTemplateTypeByteId, string $languageByteId): bool
    {
        $result = $connection->fetchOne(
            'SELECT 1 FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId AND `language_id` = :languageId',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId, 'languageId' => $languageByteId]
        );

        return !empty($result);
    }

    private function getMailTemplateId(Connection $connection, string $mailTemplateTypeByteId): ?string
    {
        $result = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    private function hasMailTemplateTranslation(Connection $connection, string $mailTemplateByteId, string $languageByteId): bool
    {
        $result = $connection->fetchOne(
            'SELECT `mail_template_id` FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId AND `language_id` = :languageId',
            ['mailTemplateId' => $mailTemplateByteId, 'languageId' => $languageByteId]
        );

        return !empty($result);
    }
}
