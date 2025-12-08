<?php declare(strict_types=1);

namespace Shopware\Administration\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class Migration1744203319MailTemplate extends MigrationStep
{
    private const GERMAN_KEY = 'Deutsch';
    private const ENGLISH_KEY = 'English';

    protected string $assetFolder = __DIR__ . '/assets/';

    public function getCreationTimestamp(): int
    {
        return 1744203319;
    }

    public function update(Connection $connection): void
    {
        $languages = $connection->fetchAllKeyValue('SELECT `name`, `id` FROM `language` WHERE `name` IN ("Deutsch", "English")');

        $mailTemplateTypeId = $this->getMailTemplateTypeId($connection);
        $this->createMailTemplateType($connection, $mailTemplateTypeId);
        $this->createMailTemplateTypeTranslations($connection, $mailTemplateTypeId, $languages);

        $mailTemplateId = $this->getMailTemplateId($connection, $mailTemplateTypeId);
        $this->createMailTemplate($connection, $mailTemplateId, $mailTemplateTypeId);
        $this->createMailTemplateTranslations($connection, $mailTemplateId, $languages);
    }

    private function createMailTemplateType(Connection $connection, string $mailTemplateTypeId): void
    {
        if ($this->mailTemplateTypeExists($connection)) {
            return;
        }

        $connection->insert('mail_template_type', [
            'id' => $mailTemplateTypeId,
            'technical_name' => 'admin_sso_user_invite',
            'available_entities' => '{}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param array<string, string> $languages
     */
    private function createMailTemplateTypeTranslations(Connection $connection, string $mailTemplateTypeId, array $languages): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $translations = [];

        if (\array_key_exists(self::ENGLISH_KEY, $languages)) {
            $translations[] = [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => $languages[self::ENGLISH_KEY],
                'name' => 'Sso user invitation',
                'created_at' => $createdAt,
            ];
        }

        if (\array_key_exists(self::GERMAN_KEY, $languages)) {
            $translations[] = [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => $languages[self::GERMAN_KEY],
                'name' => 'Sso Benutzer einladung',
                'created_at' => $createdAt,
            ];
        }

        foreach ($translations as $translation) {
            if ($this->mailTemplateTypeTranslationsExists($connection, $mailTemplateTypeId, $translation['language_id'])) {
                continue;
            }

            $connection->insert('mail_template_type_translation', $translation);
        }
    }

    private function createMailTemplate(Connection $connection, string $mailTemplateId, string $mailTemplateTypeId): void
    {
        if ($this->mailTemplateExists($connection, $mailTemplateId)) {
            return;
        }

        $connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param array<string, string> $languages
     */
    private function createMailTemplateTranslations(Connection $connection, string $mailTemplateId, array $languages): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $fs = new Filesystem();
        try {
            $translationContent = [
                'html_en' => $fs->readFile($this->assetFolder . 'sso_user_invitation_mail.en-GB.html.twig'),
                'text_en' => $fs->readFile($this->assetFolder . 'sso_user_invitation_mail.en-GB.txt'),
                'html_de' => $fs->readFile($this->assetFolder . 'sso_user_invitation_mail.de-DE.html.twig'),
                'text_de' => $fs->readFile($this->assetFolder . 'sso_user_invitation_mail.de-DE.txt'),
            ];
        } catch (IOException $e) {
            throw MigrationException::migrationError('Could not access mail template asset folder: ' . $e->getMessage());
        }

        $translations = [];
        if (\array_key_exists(self::ENGLISH_KEY, $languages)) {
            $translations[] = [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $languages[self::ENGLISH_KEY],
                'sender_name' => 'Admin',
                'subject' => '{{ nameOfInviter }} invited you to join {{ storeName }}',
                'description' => 'Shopware Sso admin Invitation',
                'content_html' => $translationContent['html_en'],
                'content_plain' => $translationContent['text_en'],
                'created_at' => $createdAt,
            ];
        }

        if (\array_key_exists(self::GERMAN_KEY, $languages)) {
            $translations[] = [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $languages[self::GERMAN_KEY],
                'sender_name' => 'Admin',
                'subject' => '{{ nameOfInviter }} hat dich eingeladen, {{ storeName }} beizutreten',
                'description' => 'Shopware Sso Admin Einladung',
                'content_html' => $translationContent['html_de'],
                'content_plain' => $translationContent['text_de'],
                'created_at' => $createdAt,
            ];
        }

        foreach ($translations as $translation) {
            if ($this->mailTemplateTranslationExists($connection, $translation['mail_template_id'], $translation['language_id'])) {
                continue;
            }

            $connection->insert('mail_template_translation', $translation);
        }
    }

    private function getMailTemplateTypeId(Connection $connection): string
    {
        $mailTemplateTypeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = "admin_sso_user_invite"'
        );

        if (!$mailTemplateTypeId) {
            $mailTemplateTypeId = Uuid::randomBytes();
        }

        return $mailTemplateTypeId;
    }

    private function mailTemplateTypeExists(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = "admin_sso_user_invite"'
        );
    }

    private function mailTemplateTypeTranslationsExists(Connection $connection, string $mailTemplateTypeId, string $languageId): bool
    {
        $result = $connection->fetchFirstColumn(
            'SELECT `name` FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId AND `language_id` = :languageId',
            [
                'mailTemplateTypeId' => $mailTemplateTypeId,
                'languageId' => $languageId,
            ]
        );

        return !empty($result);
    }

    private function getMailTemplateId(Connection $connection, string $mailTemplateTypeId): string
    {
        $mailTemplateId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND system_default = 1',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );

        if (!$mailTemplateId) {
            $mailTemplateId = Uuid::randomBytes();
        }

        return $mailTemplateId;
    }

    private function mailTemplateExists(Connection $connection, string $mailTemplateId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `id` = :mailTemplateId',
            ['mailTemplateId' => $mailTemplateId]
        );
    }

    private function mailTemplateTranslationExists(Connection $connection, string $mailTemplateId, string $languageId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT `mail_template_id` FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId AND `language_id` = :languageId',
            [
                'mailTemplateId' => $mailTemplateId,
                'languageId' => $languageId,
            ]
        );
    }
}
