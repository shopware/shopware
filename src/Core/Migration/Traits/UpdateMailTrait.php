<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
trait UpdateMailTrait
{
    use ImportTranslationsTrait;

    final protected function updateMail(MailUpdate $update, Connection $connection): void
    {
        $this->updateEnMail($connection, $update);

        $this->updateDeMail($connection, $update);
    }

    final protected function updateMailSubject(MailSubjectUpdate $update, Connection $connection): void
    {
        $this->updateEnMailSubject($connection, $update);

        $this->updateDeMailSubject($connection, $update);
    }

    private function updateDeMail(Connection $connection, MailUpdate $update): void
    {
        $languages = $this->getLanguageIds($connection, 'de-DE');
        if (!$languages) {
            return;
        }

        $translations = $this->getTranslationIds($connection, $languages, $update->getType());
        if (empty($translations)) {
            return;
        }

        foreach ($translations as $translation) {
            $connection->executeStatement(
                '
                    UPDATE mail_template_translation
                    SET content_html = :html, content_plain = :plain
                    WHERE language_id = :language_id AND mail_template_id = :template',
                [
                    'language_id' => $translation['language_id'],
                    'template' => $translation['mail_template_id'],
                    'html' => $update->getDeHtml(),
                    'plain' => $update->getDePlain(),
                ]
            );
        }
    }

    private function updateEnMail(Connection $connection, MailUpdate $update): void
    {
        $languages = array_merge([Defaults::LANGUAGE_SYSTEM], $this->getLanguageIds($connection, 'en-GB'));
        $languages = array_unique(array_filter($languages));

        if (empty($languages)) {
            return;
        }

        $translations = $this->getTranslationIds($connection, $languages, $update->getType());
        if (empty($translations)) {
            return;
        }

        foreach ($translations as $translation) {
            $connection->executeStatement(
                'UPDATE mail_template_translation
                 SET content_html = :html, content_plain = :plain
                 WHERE language_id = :language_id AND mail_template_id = :template',
                [
                    'language_id' => $translation['language_id'],
                    'template' => $translation['mail_template_id'],
                    'html' => $update->getEnHtml(),
                    'plain' => $update->getEnPlain(),
                ]
            );
        }
    }

    private function updateEnMailSubject(Connection $connection, MailSubjectUpdate $update): void
    {
        $languages = array_merge([Defaults::LANGUAGE_SYSTEM], $this->getLanguageIds($connection, 'en-GB'));
        $languages = array_unique(array_filter($languages));

        if (empty($languages)) {
            return;
        }

        $translations = $this->getTranslationIds($connection, $languages, $update->getType());
        if (empty($translations)) {
            return;
        }

        foreach ($translations as $translation) {
            $connection->executeStatement(
                'UPDATE mail_template_translation
                 SET subject = :subject
                 WHERE language_id = :language_id AND mail_template_id = :template',
                [
                    'language_id' => $translation['language_id'],
                    'template' => $translation['mail_template_id'],
                    'subject' => $update->getEnSubject(),
                ]
            );
        }
    }

    private function updateDeMailSubject(Connection $connection, MailSubjectUpdate $update): void
    {
        $languages = $this->getLanguageIds($connection, 'de-DE');
        if (!$languages) {
            return;
        }

        $translations = $this->getTranslationIds($connection, $languages, $update->getType());
        if (empty($translations)) {
            return;
        }

        foreach ($translations as $translation) {
            $connection->executeStatement(
                '
                    UPDATE mail_template_translation
                    SET subject = :subject
                    WHERE language_id = :language_id AND mail_template_id = :template',
                [
                    'language_id' => $translation['language_id'],
                    'template' => $translation['mail_template_id'],
                    'subject' => $update->getDeSubject(),
                ]
            );
        }
    }

    /**
     * @param array<string> $languageIds
     *
     * @return mixed[]
     */
    private function getTranslationIds(Connection $connection, array $languageIds, string $type): array
    {
        return $connection->fetchAllAssociative(
            '
            SELECT mail_template_translation.mail_template_id, mail_template_translation.language_id

            FROM mail_template

                INNER JOIN mail_template_translation
                    ON mail_template.id = mail_template_translation.mail_template_id

                INNER JOIN mail_template_type
                    ON mail_template.mail_template_type_id = mail_template_type.id

            WHERE mail_template_translation.language_id IN (:ids)
            AND mail_template_type.technical_name = :type
            AND mail_template.system_default = 1
            AND mail_template_translation.updated_at IS NULL
            AND mail_template.updated_at IS NULL',
            ['ids' => Uuid::fromHexToBytesList($languageIds), 'type' => $type],
            ['ids' => ArrayParameterType::STRING]
        );
    }
}
