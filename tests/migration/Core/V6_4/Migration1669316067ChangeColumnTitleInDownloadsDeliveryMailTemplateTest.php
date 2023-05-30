<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1669316067ChangeColumnTitleInDownloadsDeliveryMailTemplate;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1669316067ChangeColumnTitleInDownloadsDeliveryMailTemplate
 */
class Migration1669316067ChangeColumnTitleInDownloadsDeliveryMailTemplateTest extends TestCase
{
    use MigrationTestTrait;

    /**
     * @throws Exception
     */
    public function testMailTemplatesAreUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1669316067ChangeColumnTitleInDownloadsDeliveryMailTemplate();
        $migration->update($connection);

        $deLangId = $this->fetchLanguageId($connection, 'de-DE');
        $enLangId = $this->fetchLanguageId($connection, 'en-GB');
        static::assertIsString($deLangId);
        static::assertIsString($enLangId);

        $mailTemplateTypeId = $connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `mail_template_type` WHERE `technical_name` = :type',
            ['type' => MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY]
        );
        static::assertIsString($mailTemplateTypeId);

        $mailTemplateId = $connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `mail_template` WHERE `mail_template_type_id` = :id',
            ['id' => Uuid::fromHexToBytes($mailTemplateTypeId)]
        );
        static::assertIsString($mailTemplateId);

        $template = [
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/downloads_delivery/en-plain.html.twig'),
            'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/downloads_delivery/en-html.html.twig'),
            'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/downloads_delivery/de-plain.html.twig'),
            'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/downloads_delivery/de-html.html.twig'),
        ];

        $mailTemplateTranslationsDe = $this->getMailTemplateTranslation($connection, $template, $deLangId);
        static::assertEquals($template['htmlDe'], $mailTemplateTranslationsDe['content_html']);
        static::assertEquals($template['plainDe'], $mailTemplateTranslationsDe['content_plain']);

        $mailTemplateTranslationsEn = $this->getMailTemplateTranslation($connection, $template, $enLangId);
        static::assertEquals($template['htmlEn'], $mailTemplateTranslationsEn['content_html']);
        static::assertEquals($template['plainEn'], $mailTemplateTranslationsEn['content_plain']);
    }

    /**
     * @throws Exception
     */
    private function fetchLanguageId(Connection $connection, string $langCode): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $langCode]) ?: null;
    }

    /**
     * @param array<string, string> $template
     *
     * @throws Exception
     *
     * @return array<string, string>
     */
    private function getMailTemplateTranslation(Connection $connection, array $template, string $langId): array
    {
        $sqlString = 'SELECT `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        static::assertNotFalse($template['id']);

        $translation = $connection->fetchAssociative($sqlString, [
            'langId' => $langId,
            'templateId' => $template['id'],
        ]);

        if (!$translation) {
            static::fail('mail template content empty');
        }

        return $translation;
    }
}
