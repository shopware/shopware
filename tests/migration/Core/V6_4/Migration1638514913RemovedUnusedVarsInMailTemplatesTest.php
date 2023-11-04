<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1638514913RemovedUnusedVarsInMailTemplates;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1638514913RemovedUnusedVarsInMailTemplates
 */
class Migration1638514913RemovedUnusedVarsInMailTemplatesTest extends TestCase
{
    public function testEnSignupMailTemplateIsUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        /** @var string $enLangId */
        $enLangId = $this->fetchLanguageId($connection);

        static::assertNotNull($enLangId);

        $migration = new Migration1638514913RemovedUnusedVarsInMailTemplates();
        $migration->update($connection);

        /** @var string $passwordChangeTemplateId */
        $passwordChangeTemplateId = $this->fetchSystemMailTemplateIdFromType(
            $connection,
            MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE
        );
        static::assertNotNull($passwordChangeTemplateId);

        $sqlString = <<<'SQL'
            SELECT `subject`, `content_plain`, `content_html`
            FROM `mail_template_translation`
            WHERE `mail_template_id`= :templateId
              AND `language_id` = :langId
              AND `updated_at` IS NULL
        SQL;

        $passwordChangeTemplateTranslation = $connection->fetchAssociative($sqlString, [
            'langId' => $enLangId,
            'templateId' => $passwordChangeTemplateId,
        ]);

        $templates = $this->getPasswordChangeTemplates();

        // Only assert in case the template is not updated
        if (!empty($passwordChangeTemplateTranslation)) {
            static::assertEquals('Password reset - {{ salesChannel.name }}', $passwordChangeTemplateTranslation['subject']);
            static::assertEquals($templates['html']['en'], $passwordChangeTemplateTranslation['content_html']);
            static::assertEquals($templates['plain']['en'], $passwordChangeTemplateTranslation['content_plain']);
        }

        $deLangId = $this->fetchLanguageId($connection, 'de-DE');

        $passwordChangeTemplateDeTranslation = $connection->fetchAssociative($sqlString, [
            'langId' => $deLangId,
            'templateId' => $passwordChangeTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($passwordChangeTemplateDeTranslation)) {
            static::assertEquals($templates['html']['de'], $passwordChangeTemplateDeTranslation['content_html']);
            static::assertEquals($templates['plain']['de'], $passwordChangeTemplateDeTranslation['content_plain']);
        }
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
            SELECT `id`
            FROM `mail_template_type`
            WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchOne();

        $templateId = $connection->executeQuery('
            SELECT `id`
            FROM `mail_template`
            WHERE `mail_template_type_id` = :typeId
              AND `system_default` = 1
              AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchLanguageId(Connection $connection, string $localeCode = 'en-GB'): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $localeCode]) ?: null;
    }

    /**
     * @return string[][]
     */
    private function getPasswordChangeTemplates(): array
    {
        return [
            'html' => [
                'en' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/password_change/en-html.html.twig'),
                'de' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/password_change/de-html.html.twig'),
            ],
            'plain' => [
                'en' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/password_change/en-plain.html.twig'),
                'de' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/password_change/de-plain.html.twig'),
            ],
        ];
    }
}
