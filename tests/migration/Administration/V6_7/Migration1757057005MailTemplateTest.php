<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Administration\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Migration\V6_7\Migration1757057005MailTemplate;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1757057005MailTemplate::class)]
class Migration1757057005MailTemplateTest extends TestCase
{
    use UpdateMailTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        // prepare the test
        $mailTemplateTypeId = $this->getTemplateTypeId();
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        $mailTranslations = new MailUpdate(
            'admin_sso_user_invite',
            'en plain text',
            '<h1>en HTML</h1>',
            'de plain text',
            '<h1>de HTML</h1>',
        );

        $this->updateMail($mailTranslations, $this->connection);

        $translations = $this->getMailTemplateTranslations($mailTemplateId);
        static::assertSame('en plain text', $translations->enPlain);
        static::assertSame('<h1>en HTML</h1>', $translations->enHtml);
        static::assertSame('de plain text', $translations->dePlain);
        static::assertSame('<h1>de HTML</h1>', $translations->deHtml);

        // Start with the test
        $migration = new Migration1757057005MailTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $translations = $this->getMailTemplateTranslations($mailTemplateId);
        static::assertStringContainsString('To get access to the store, please either log in or sign up using the email address', $translations->enPlain);
        static::assertStringContainsString('<p>To get access to the store, please either log in or sign up using the email address', $translations->enHtml);
        static::assertStringContainsString('Um Zugriff auf den Shop zu erhalten, logge Dich bitte entweder ein oder registriere Dich mit der E-Mail-Adresse', $translations->dePlain);
        static::assertStringContainsString('<p>Um Zugriff auf den Shop zu erhalten, logge Dich bitte entweder ein oder registriere Dich mit der E-Mail-Adresse', $translations->deHtml);
    }

    private function getMailTemplateTranslations(string $mailTemplateId): \stdClass
    {
        $languages = $this->connection->fetchAllKeyValue('SELECT `name`, `id` FROM `language` WHERE `name` IN ("Deutsch", "English")');

        $translationArray = $this->connection->fetchAllAssociative(
            'SELECT `language_id`, `content_html`, `content_plain`  FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId',
            [
                'mailTemplateId' => $mailTemplateId,
            ]
        );

        $translations = new \stdClass();
        foreach ($languages as $language => $languageId) {
            foreach ($translationArray as $translation) {
                if ($language === 'Deutsch' && $translation['language_id'] === $languageId) {
                    $translations->dePlain = $translation['content_plain'];
                    $translations->deHtml = $translation['content_html'];
                }

                if ($language === 'English' && $translation['language_id'] === $languageId) {
                    $translations->enPlain = $translation['content_plain'];
                    $translations->enHtml = $translation['content_html'];
                }
            }
        }

        return $translations;
    }

    private function getTemplateTypeId(): string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = "admin_sso_user_invite"'
        );

        if (!$result) {
            static::fail('Mail template type id is null');
        }

        return $result;
    }

    private function getMailTemplateId(string $mailTemplateTypeId): string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND system_default = 1',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );

        if (!$result) {
            static::fail('Mail template id not found');
        }

        return $result;
    }
}
