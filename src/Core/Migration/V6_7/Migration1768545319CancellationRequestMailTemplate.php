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
class Migration1768545319CancellationRequestMailTemplate extends MigrationStep
{
    public const MERCHANT_DIRECTORY = 'cancellation_request.merchant';
    public const CUSTOMER_DIRECTORY = 'cancellation_request.customer';

    /**
     * @var array{
     *     en_name: string,
     *     de_name: string,
     * }
     */
    public const MAIL_TEMPLATE_TYPE_MERCHANT_TRANSLATIONS = [
        'en_name' => 'Cancellation request received',
        'de_name' => 'Wiederrufsantrag erhalten',
    ];

    /**
     * @var array{
     *     en_name: string,
     *     de_name: string,
     * }
     */
    public const MAIL_TEMPLATE_TYPE_CUSTOMER_TRANSLATIONS = [
        'en_name' => 'Cancellation request requested',
        'de_name' => 'Wiederrufsantrag gestellt',
    ];

    /**
     * @var array{
     *     en_subject: string,
     *     de_subject: string,
     *     en_description: string,
     *     de_description: string
     * }
     */
    public const MAIL_TEMPLATE_TRANSLATIONS_MERCHANT = [
        'en_subject' => 'Cancellation request received',
        'de_subject' => 'Wiederrufsantrag erhalten',
        'en_description' => 'Received cancellation request from customer',
        'de_description' => 'Wiederrufsantrag von Kunden erhalten',
    ];

    /**
     * @var array{
     *     en_subject: string,
     *     de_subject: string,
     *     en_description: string,
     *     de_description: string
     * }
     */
    public const MAIL_TEMPLATE_TRANSLATIONS_CUSTOMER = [
        'en_subject' => 'Cancellation request sent',
        'de_subject' => 'Wiederrufsantrag gesendet',
        'en_description' => 'Confirmation receipt of customers cancellation request',
        'de_description' => 'Empfangsbestätigung für Wiederrufsantrag des Kunden',
    ];

    public function getCreationTimestamp(): int
    {
        return 1768545319;
    }

    public function update(Connection $connection): void
    {
        $enLanguageByteId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLanguageByteId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $merchantMailTemplateTypeByteId = $this->createMailTemplateType(
            $connection,
            self::MAIL_TEMPLATE_TYPE_MERCHANT_TRANSLATIONS,
            MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST_MERCHANT,
            $enLanguageByteId,
            $deLanguageByteId
        );
        $merchantMailStruct = $this->createMailStruct(self::MERCHANT_DIRECTORY, MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST_MERCHANT);
        $this->createMailTemplate(
            $connection,
            $merchantMailTemplateTypeByteId,
            self::MAIL_TEMPLATE_TRANSLATIONS_MERCHANT,
            $merchantMailStruct,
            $enLanguageByteId,
            $deLanguageByteId
        );

        $customerMailTemplateTypeByteId = $this->createMailTemplateType(
            $connection,
            self::MAIL_TEMPLATE_TYPE_CUSTOMER_TRANSLATIONS,
            MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST_CUSTOMER,
            $enLanguageByteId,
            $deLanguageByteId
        );
        $customerMailStruct = $this->createMailStruct(self::CUSTOMER_DIRECTORY, MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST_CUSTOMER);
        $this->createMailTemplate(
            $connection,
            $customerMailTemplateTypeByteId,
            self::MAIL_TEMPLATE_TRANSLATIONS_CUSTOMER,
            $customerMailStruct,
            $enLanguageByteId,
            $deLanguageByteId
        );
    }

    /**
     * @param array{en_subject: string, de_subject: string, en_description: string, de_description: string} $translations
     */
    private function createMailTemplate(
        Connection $connection,
        string $mailTemplateTypeByteId,
        array $translations,
        MailStruct $mailStruct,
        ?string $enLanguageByteId,
        ?string $deLanguageByteId
    ): void {
        $hasMailTemplate = true;
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
                    'subject' => $translations['en_subject'],
                    'description' => $translations['en_description'],
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
                    'subject' => $translations['de_subject'],
                    'description' => $translations['de_description'],
                    'content_html' => $mailStruct->getDeHtml(),
                    'content_plain' => $mailStruct->getDePlain(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function createMailStruct(string $directory, string $mailType): MailStruct
    {
        $filesystem = new Filesystem();

        $mailStruct = new MailStruct($mailType);
        $mailStruct->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $directory . '/en-html.html.twig'));
        $mailStruct->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $directory . '/en-plain.txt.twig'));
        $mailStruct->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $directory . '/de-html.html.twig'));
        $mailStruct->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/' . $directory . '/de-plain.txt.twig'));

        return $mailStruct;
    }

    /**
     * @param array{en_name: string, de_name: string} $translations
     */
    private function createMailTemplateType(
        Connection $connection,
        array $translations,
        string $mailTemplateTypeTechnicalName,
        ?string $enLanguageByteId,
        ?string $deLanguageByteId
    ): string {
        $hasMailTemplateType = true;
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection, $mailTemplateTypeTechnicalName);
        if (empty($mailTemplateTypeByteId)) {
            $mailTemplateTypeByteId = Uuid::randomBytes();
            $hasMailTemplateType = false;
        }

        if (!$hasMailTemplateType) {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => $mailTemplateTypeByteId,
                    'technical_name' => $mailTemplateTypeTechnicalName,
                    'available_entities' => '[]',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if (\is_string($enLanguageByteId) && !$this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $enLanguageByteId)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailTemplateTypeByteId,
                    'name' => $translations['en_name'],
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
                    'name' => $translations['de_name'],
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

    private function getMailTemplateTypeId(Connection $connection, string $technicalName): ?string
    {
        $result = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
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
