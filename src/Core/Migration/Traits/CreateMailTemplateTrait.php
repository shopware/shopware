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
        $germanLanguageByteIds = $this->getLanguageByteIdsByLocalePrefix($connection, 'de');

        // The system default language must always be filled. It is therefore added to the english
        // language ids (and removed again if it actually is a german language), so its translation is
        // never skipped, regardless of which locale the default language uses.
        $englishLanguageByteIds = array_values(array_unique(array_diff(
            array_merge($this->getLanguageByteIdsByLocalePrefix($connection, 'de', true), [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]),
            $germanLanguageByteIds
        )));

        $mailCreationState = new MailCreationState();
        $mailCreationState->setEnglishLanguageByteIds($englishLanguageByteIds);
        $mailCreationState->setGermanLanguageByteIds($germanLanguageByteIds);

        $this->createMailTemplateType($connection, $mailTemplateType, $mailCreationState);
        $this->createMailTemplate($connection, $mailTemplate, $mailCreationState);
    }

    private function createMailTemplateType(
        Connection $connection,
        MailTemplateTypeCreateStruct $mailTemplateType,
        MailCreationState $mailCreationState,
    ): void {
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection, $mailTemplateType->getTechnicalName());
        if ($mailTemplateTypeByteId === null || $mailTemplateTypeByteId === '') {
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

        foreach ($mailCreationState->getEnglishLanguageByteIds() as $languageByteId) {
            if ($this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $languageByteId)) {
                continue;
            }

            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailCreationState->getMailTemplateTypeByteId(),
                    'name' => $mailTemplateType->getEnName(),
                    'language_id' => $languageByteId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        foreach ($mailCreationState->getGermanLanguageByteIds() as $languageByteId) {
            if ($this->hasTemplateTypeTranslation($connection, $mailTemplateTypeByteId, $languageByteId)) {
                continue;
            }

            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailCreationState->getMailTemplateTypeByteId(),
                    'name' => $mailTemplateType->getDeName(),
                    'language_id' => $languageByteId,
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
        if ($mailTemplateByteId === null || $mailTemplateByteId === '') {
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

        foreach ($mailCreationState->getEnglishLanguageByteIds() as $languageByteId) {
            if ($this->hasMailTemplateTranslation($connection, $mailTemplateByteId, $languageByteId)) {
                continue;
            }

            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailCreationState->getMailTemplateByteId(),
                    'language_id' => $languageByteId,
                    'sender_name' => $mailCreateStruct->getEnSenderName(),
                    'subject' => $mailCreateStruct->getEnSubject(),
                    'description' => $mailCreateStruct->getEnDescription(),
                    'content_html' => $mailCreateStruct->getEnHtml(),
                    'content_plain' => $mailCreateStruct->getEnPlain(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        foreach ($mailCreationState->getGermanLanguageByteIds() as $languageByteId) {
            if ($this->hasMailTemplateTranslation($connection, $mailTemplateByteId, $languageByteId)) {
                continue;
            }

            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailCreationState->getMailTemplateByteId(),
                    'language_id' => $languageByteId,
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
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId AND `language_id` = :languageId',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId, 'languageId' => $languageByteId]
        );
    }

    private function getMailTemplateId(Connection $connection, ?string $mailTemplateTypeByteId): ?string
    {
        $result = $connection->fetchOne(
            <<<'SQL'
SELECT `id`
FROM `mail_template`
WHERE `mail_template_type_id` = :mailTemplateTypeId
    AND `system_default` = 1
ORDER BY `created_at` ASC, `id` ASC
LIMIT 1
SQL,
            ['mailTemplateTypeId' => $mailTemplateTypeByteId]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    private function hasMailTemplateTranslation(Connection $connection, string $mailTemplateByteId, string $languageByteId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT `mail_template_id` FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId AND `language_id` = :languageId',
            ['mailTemplateId' => $mailTemplateByteId, 'languageId' => $languageByteId]
        );
    }

    /**
     * @return list<string>
     */
    private function getLanguageByteIdsByLocalePrefix(Connection $connection, string $localePrefix, bool $invert = false): array
    {
        $operator = $invert ? 'NOT LIKE' : 'LIKE';

        $languageIds = $connection->fetchFirstColumn(
            \sprintf(
                <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE LOWER(`locale`.`code`) %s :localePrefix
ORDER BY `language`.`created_at` ASC, `language`.`id` ASC
SQL,
                $operator
            ),
            ['localePrefix' => $localePrefix . '-%']
        );
        $languageByteIds = [];
        foreach ($languageIds as $languageId) {
            if (!\is_string($languageId)) {
                continue;
            }

            $languageByteIds[] = $languageId;
        }

        return $languageByteIds;
    }
}
