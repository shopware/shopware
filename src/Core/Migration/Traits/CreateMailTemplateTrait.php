<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Structs\MailCreationState;
use Shopware\Core\Migration\Structs\MailTemplateCreateStruct;
use Shopware\Core\Migration\Structs\MailTemplateTypeCreateStruct;

trait CreateMailTemplateTrait
{
    protected function createMail(
        Connection $connection,
        MailTemplateTypeCreateStruct $mailTemplateType,
        MailTemplateCreateStruct $mailTemplate,
    ): void {
        $mailCreationState = new MailCreationState();
        $mailCreationState->setEnLanguageByteId($this->getLanguageIdByLocale($connection, 'en-GB'));
        $mailCreationState->setDeLanguageByteId($this->getLanguageIdByLocale($connection, 'de-DE'));

        $this->createMailTemplateType($connection, $mailTemplateType, $mailCreationState);
        $this->createMailTemplate($connection, $mailTemplate, $mailCreationState);
    }

    private function createMailTemplateType(
        Connection $connection,
        MailTemplateTypeCreateStruct $mailTemplateType,
        MailCreationState $mailCreationState,
    ): void {
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection, $mailTemplateType->getTechnicalName());
        if (empty($mailTemplateTypeByteId)) {
            $mailCreationState->mailTemplateTypeDoesNotExist();
            $mailTemplateTypeByteId = Uuid::randomBytes();
        }

        $mailCreationState->setMailTemplateTypeByteId($mailTemplateTypeByteId);

        if (!$mailCreationState->mailTemplateTypeExists()) {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => $mailCreationState->getMailTemplateTypeByteId(),
                    'technical_name' => $mailTemplateType->getTechnicalName(),
                    'available_entities' => \json_encode($mailTemplateType->getAvailableEntities(), \JSON_THROW_ON_ERROR),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($mailCreationState->hasEnLanguageByteId() && !$this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $mailCreationState->getEnLanguageByteId() ?? '')) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailCreationState->getMailTemplateTypeByteId(),
                    'name' => $mailTemplateType->getEnName(),
                    'language_id' => $mailCreationState->getEnLanguageByteId(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($mailCreationState->hasDeLanguageByteId() && !$this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $mailCreationState->getDeLanguageByteId() ?? '')) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailCreationState->getMailTemplateTypeByteId(),
                    'name' => $mailTemplateType->getDeName(),
                    'language_id' => $mailCreationState->getDeLanguageByteId(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function createMailTemplate(
        Connection $connection,
        MailTemplateCreateStruct $mailCreateStruct,
        MailCreationState $mailCreationState,
    ): void {
        $mailTemplateByteId = $this->getMailTemplateId($connection, $mailCreationState->getMailTemplateTypeByteId());
        if (empty($mailTemplateByteId)) {
            $mailCreationState->mailTemplateDoesNotExist();
            $mailTemplateByteId = Uuid::randomBytes();
        }

        $mailCreationState->setMailTemplateByteId($mailTemplateByteId);

        if (!$mailCreationState->mailTemplateExists()) {
            $connection->insert(
                'mail_template',
                [
                    'id' => $mailCreationState->getMailTemplateByteId(),
                    'mail_template_type_id' => $mailCreationState->getMailTemplateTypeByteId(),
                    'system_default' => $mailCreateStruct->isSystemDefault(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($mailCreationState->hasEnLanguageByteId() && !$this->hasMailTemplateTranslation($connection, $mailTemplateByteId, $mailCreationState->getEnLanguageByteId() ?? '')) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailCreationState->getMailTemplateByteId(),
                    'language_id' => $mailCreationState->getEnLanguageByteId(),
                    'sender_name' => $mailCreateStruct->getEnSenderName(),
                    'subject' => $mailCreateStruct->getEnSubject(),
                    'description' => $mailCreateStruct->getEnDescription(),
                    'content_html' => $mailCreateStruct->getEnHtml(),
                    'content_plain' => $mailCreateStruct->getEnPlain(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($mailCreationState->hasDeLanguageByteId() && !$this->hasMailTemplateTranslation($connection, $mailTemplateByteId, $mailCreationState->getDeLanguageByteId() ?? '')) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailCreationState->getMailTemplateByteId(),
                    'language_id' => $mailCreationState->getDeLanguageByteId(),
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
