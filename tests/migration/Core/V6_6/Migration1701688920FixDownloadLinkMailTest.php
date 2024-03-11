<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1701688920FixDownloadLinkMail;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1701688920FixDownloadLinkMail::class)]
class Migration1701688920FixDownloadLinkMailTest extends TestCase
{
    use MigrationTestTrait;

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1701688920FixDownloadLinkMail();
        static::assertSame(1701688920, $migration->getCreationTimestamp());
    }

    public function testMailTemplateUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1701688920FixDownloadLinkMail();
        $migration->update($connection);

        $deLangId = $this->fetchLanguageId($connection, 'de-DE');
        $enLangId = $this->fetchLanguageId($connection, 'en-GB');
        static::assertNotNull($deLangId);
        static::assertNotNull($enLangId);

        $template = [
            'id' => $this->fetchSystemMailTemplateIdFromType(
                $connection,
                MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY
            ),
            'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/downloads_delivery/de-html.html.twig'),
            'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/downloads_delivery/de-plain.html.twig'),
            'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/downloads_delivery/en-html.html.twig'),
            'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/downloads_delivery/en-plain.html.twig'),
        ];

        $mailTemplateTranslationDe = $this->getMailTemplateTranslation(
            $connection,
            $template,
            $deLangId
        );

        static::assertEquals($mailTemplateTranslationDe['htmlDe'], $mailTemplateTranslationDe['content_html']);
        static::assertEquals($mailTemplateTranslationDe['plainDe'], $mailTemplateTranslationDe['content_plain']);

        $mailTemplateTranslationEn = $this->getMailTemplateTranslation(
            $connection,
            $template,
            $enLangId
        );

        static::assertEquals($mailTemplateTranslationEn['htmlEn'], $mailTemplateTranslationEn['content_html']);
        static::assertEquals($mailTemplateTranslationEn['plainEn'], $mailTemplateTranslationEn['content_plain']);
    }

    /**
     * @param array<string, string|null> $template
     *
     * @throws Exception
     *
     * @return array<string, string>
     */
    private function getMailTemplateTranslation(Connection $connection, array $template, string $langId): array
    {
        $sqlString = 'SELECT `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        static::assertNotNull($template['id']);
        $translation = $connection->fetchAssociative($sqlString, [
            'langId' => $langId,
            'templateId' => $template['id'],
        ]);

        if (!$translation) {
            static::fail('mail template content empty');
        }

        return array_merge($template, ['content_html' => $translation['content_html'], 'content_plain' => $translation['content_plain']]);
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $type): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $type])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    /**
     * @throws Exception
     */
    private function fetchLanguageId(Connection $connection, string $code): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]) ?: null;
    }
}
