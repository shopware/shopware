<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Structs\MailCreateResult;
use Shopware\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopware\Core\Migration\Structs\MailTemplateTypeCreateStruct;

trait CreateMailTemplateTrait
{
    private string $enLanguageByteId;

    private string $deLanguageByteId;

    protected function createMail(
        Connection $connection,
        MailTemplateTypeCreateStruct $mailTemplateType,
        MailTemplateCreateStruct $mailTemplate,
    ): void {
        $mailCreateResult = new MailCreateResult();
        $mailCreateResult->setEnLanguageByteId($this->getLanguageIdByLocale($connection, 'en-GB'));
        $mailCreateResult->setDeLanguageByteId($this->getLanguageIdByLocale($connection, 'de-DE'));

        $this->createMailTemplateType($connection, $mailTemplateType, $mailCreateResult);
        $this->createMailTemplate($connection, $mailTemplate, $mailCreateResult);
    }

    private function createMailTemplateType(
        Connection $connection,
        MailTemplateTypeCreateStruct $mailTemplateType,
        MailCreateResult $mailCreateResult,
    ): void {
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection, $mailTemplateType->getTechnicalName());
        if (empty($mailTemplateTypeByteId)) {
            $mailCreateResult->mailTemplateTypeDoesNotExist();
            $mailTemplateTypeByteId = Uuid::randomBytes();
        }

        $mailCreateResult->setMailTemplateTypeByteId($mailTemplateTypeByteId);

        if (!$mailCreateResult->isMailTemplateTypeAlreadyExists()) {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => $mailCreateResult->getMailTemplateTypeByteId(),
                    'technical_name' => $mailTemplateType->getTechnicalName(),
                    'available_entities' => \json_encode($mailTemplateType->getAvailableEntities(), \JSON_THROW_ON_ERROR),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($mailCreateResult->hasEnLanguageByteId() && !$this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $mailCreateResult->getEnLanguageByteId() ?? '')) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailCreateResult->getMailTemplateTypeByteId(),
                    'name' => $mailTemplateType->getEnName(),
                    'language_id' => $mailCreateResult->getEnLanguageByteId(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($mailCreateResult->hasDeLanguageByteId() && !$this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $mailCreateResult->getDeLanguageByteId() ?? '')) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailCreateResult->getMailTemplateTypeByteId(),
                    'name' => $mailTemplateType->getDeName(),
                    'language_id' => $mailCreateResult->getDeLanguageByteId(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function createMailTemplate(
        Connection $connection,
        MailTemplateCreateStruct $mailCreateStruct,
        MailCreateResult $mailCreateResult,
    ): void {
        $mailTemplateByteId = $this->getMailTemplateId($connection, $mailCreateResult->getMailTemplateTypeByteId());
        if (empty($mailTemplateByteId)) {
            $mailCreateResult->mailTemplateDoesNotExist();
            $mailTemplateByteId = Uuid::randomBytes();
        }

        $mailCreateResult->setMailTemplateByteId($mailTemplateByteId);

        if (!$mailCreateResult->isMailTemplateAlreadyExists()) {
            $connection->insert(
                'mail_template',
                [
                    'id' => $mailCreateResult->getMailTemplateByteId(),
                    'mail_template_type_id' => $mailCreateResult->getMailTemplateTypeByteId(),
                    'system_default' => $mailCreateStruct->isSystemDefault(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($mailCreateResult->hasEnLanguageByteId() && !$this->hasMailTemplateTranslation($connection, $mailTemplateByteId, $mailCreateResult->getEnLanguageByteId() ?? '')) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailCreateResult->getMailTemplateByteId(),
                    'language_id' => $mailCreateResult->getEnLanguageByteId(),
                    'sender_name' => $mailCreateStruct->getEnSenderName(),
                    'subject' => $mailCreateStruct->getEnSubject(),
                    'description' => $mailCreateStruct->getEnDescription(),
                    'content_html' => $mailCreateStruct->getEnHtml(),
                    'content_plain' => $mailCreateStruct->getEnPlain(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($mailCreateResult->hasDeLanguageByteId() && !$this->hasMailTemplateTranslation($connection, $mailTemplateByteId, $mailCreateResult->getDeLanguageByteId() ?? '')) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailCreateResult->getMailTemplateByteId(),
                    'language_id' => $mailCreateResult->getDeLanguageByteId(),
                    'sender_name' => $mailCreateStruct->getDeSenderName(),
                    'subject' => $mailCreateStruct->getDeSubject(),
                    'description' => $mailCreateStruct->getDeDescription(),
                    'content_html' => $mailCreateStruct->getDeHtml(),
                    'content_plain' => $mailCreateStruct->getDePlain(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
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

    private function getMailTemplateId(Connection $connection, ?string $mailTemplateTypeByteId): ?string
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
