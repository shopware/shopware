<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\CreateMailTemplateTrait;

/**
 * Repairs mail templates and mail template types that are missing a translation for the system
 * default language.
 *
 * On systems whose default language uses a locale other than en-GB or de-DE, the
 * {@see CreateMailTemplateTrait} used to write the english content
 * only to a separate en-GB language and never to the system default language. The translation for
 * the default language is therefore missing.
 *
 * This migration backfills the missing system default language translation by copying the english
 * (en-GB) content. Records that already have a translation for the system default language are left
 * untouched.
 *
 * @internal
 */
#[Package('after-sales')]
class Migration1781654400RepairDefaultLanguageMailTranslations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1781654400;
    }

    public function update(Connection $connection): void
    {
        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $englishLanguageId = $this->getEnglishLanguageId($connection);

        // Nothing to repair if there is no separate en-GB language to copy the english content from,
        // or if the system default language itself is the en-GB language (then it is already filled).
        if ($englishLanguageId === null || $englishLanguageId === $defaultLanguageId) {
            return;
        }

        $this->repairMailTemplateTypeTranslations($connection, $englishLanguageId, $defaultLanguageId);
        $this->repairMailTemplateTranslations($connection, $englishLanguageId, $defaultLanguageId);
    }

    private function repairMailTemplateTypeTranslations(Connection $connection, string $englishLanguageId, string $defaultLanguageId): void
    {
        $missingTranslations = $connection->fetchAllAssociative(
            'SELECT `source`.`mail_template_type_id`, `source`.`name`, `source`.`custom_fields`
             FROM `mail_template_type_translation` AS `source`
             WHERE `source`.`language_id` = :englishLanguageId
               AND NOT EXISTS (
                   SELECT 1
                   FROM `mail_template_type_translation` AS `existing`
                   WHERE `existing`.`mail_template_type_id` = `source`.`mail_template_type_id`
                     AND `existing`.`language_id` = :defaultLanguageId
               )',
            ['englishLanguageId' => $englishLanguageId, 'defaultLanguageId' => $defaultLanguageId]
        );

        foreach ($missingTranslations as $translation) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $translation['mail_template_type_id'],
                    'language_id' => $defaultLanguageId,
                    'name' => $translation['name'],
                    'custom_fields' => $translation['custom_fields'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function repairMailTemplateTranslations(Connection $connection, string $englishLanguageId, string $defaultLanguageId): void
    {
        $missingTranslations = $connection->fetchAllAssociative(
            'SELECT `source`.`mail_template_id`, `source`.`sender_name`, `source`.`subject`, `source`.`description`, `source`.`content_html`, `source`.`content_plain`
             FROM `mail_template_translation` AS `source`
             WHERE `source`.`language_id` = :englishLanguageId
               AND NOT EXISTS (
                   SELECT 1
                   FROM `mail_template_translation` AS `existing`
                   WHERE `existing`.`mail_template_id` = `source`.`mail_template_id`
                     AND `existing`.`language_id` = :defaultLanguageId
               )',
            ['englishLanguageId' => $englishLanguageId, 'defaultLanguageId' => $defaultLanguageId]
        );

        foreach ($missingTranslations as $translation) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $translation['mail_template_id'],
                    'language_id' => $defaultLanguageId,
                    'sender_name' => $translation['sender_name'],
                    'subject' => $translation['subject'],
                    'description' => $translation['description'],
                    'content_html' => $translation['content_html'],
                    'content_plain' => $translation['content_plain'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function getEnglishLanguageId(Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            'SELECT `language`.`id`
             FROM `language`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
             WHERE `locale`.`code` = :code
             LIMIT 1',
            ['code' => 'en-GB']
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }
}
