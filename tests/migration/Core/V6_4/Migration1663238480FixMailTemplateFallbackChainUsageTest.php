<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1663238480FixMailTemplateFallbackChainUsage;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1663238480FixMailTemplateFallbackChainUsage
 */
class Migration1663238480FixMailTemplateFallbackChainUsageTest extends TestCase
{
    use MigrationTestTrait;

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function testMailTemplatesAreUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1663238480FixMailTemplateFallbackChainUsage();
        $migration->update($connection);

        $deLangId = $this->fetchDeLanguageId($connection);
        $enLandId = $this->fetchEnLanguageId($connection);
        static::assertNotNull($deLangId);
        static::assertNotNull($enLandId);

        $templates = [
            [
                'id' => $this->fetchSystemMailTemplateIdFromType(
                    $connection,
                    MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_ACCEPTED
                ),
                'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer.group.registration.accepted/de-html.html.twig'),
                'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer.group.registration.accepted/de-plain.html.twig'),
                'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer.group.registration.accepted/en-html.html.twig'),
                'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer.group.registration.accepted/en-plain.html.twig'),
            ],
            [
                'id' => $this->fetchSystemMailTemplateIdFromType(
                    $connection,
                    MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_DECLINED
                ),
                'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer.group.registration.declined/de-html.html.twig'),
                'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer.group.registration.declined/de-plain.html.twig'),
                'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer.group.registration.declined/en-html.html.twig'),
                'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer.group.registration.declined/en-plain.html.twig'),
            ],
            [
                'id' => $this->fetchSystemMailTemplateIdFromType(
                    $connection,
                    MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_ACCEPT
                ),
                'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer_group_change_accept/de-html.html.twig'),
                'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer_group_change_accept/de-plain.html.twig'),
                'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer_group_change_accept/en-html.html.twig'),
                'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer_group_change_accept/en-plain.html.twig'),
            ],
            [
                'id' => $this->fetchSystemMailTemplateIdFromType(
                    $connection,
                    MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_REJECT
                ),
                'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer_group_change_reject/de-html.html.twig'),
                'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer_group_change_reject/de-plain.html.twig'),
                'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer_group_change_reject/en-html.html.twig'),
                'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/customer_group_change_reject/en-plain.html.twig'),
            ],
            [
                'id' => $this->fetchSystemMailTemplateIdFromType(
                    $connection,
                    MailTemplateTypes::MAILTYPE_GUEST_ORDER_DOUBLE_OPT_IN
                ),
                'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/guest_order.double_opt_in/de-html.html.twig'),
                'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/guest_order.double_opt_in/de-plain.html.twig'),
                'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/guest_order.double_opt_in/en-html.html.twig'),
                'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/guest_order.double_opt_in/en-plain.html.twig'),
            ],
        ];

        $mailTemplateTranslationsDe = $this->getMailTemplateTranslations(
            $connection,
            $templates,
            $deLangId
        );

        foreach ($mailTemplateTranslationsDe as $mailTranslation) {
            static::assertEquals($mailTranslation['htmlDe'], $mailTranslation['content_html']);
            static::assertEquals($mailTranslation['plainDe'], $mailTranslation['content_plain']);
        }

        $mailTemplateTranslationsEn = $this->getMailTemplateTranslations(
            $connection,
            $templates,
            $enLandId
        );

        foreach ($mailTemplateTranslationsEn as $mailTranslation) {
            static::assertEquals($mailTranslation['htmlEn'], $mailTranslation['content_html']);
            static::assertEquals($mailTranslation['plainEn'], $mailTranslation['content_plain']);
        }
    }

    /**
     * @param array<int, array<string, string|null>> $templates
     *
     * @throws Exception
     *
     * @return array<int, array<string, string>>
     */
    private function getMailTemplateTranslations(Connection $connection, array $templates, string $langId): array
    {
        $sqlString = 'SELECT `subject`, `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $translations = [];
        foreach ($templates as $template) {
            static::assertNotNull($template['id']);
            $translation = $connection->fetchAssociative($sqlString, [
                'langId' => $langId,
                'templateId' => $template['id'],
            ]);

            if (!$translation) {
                static::fail('mail template content empty');
            }

            array_push($translations, array_merge($template, ['content_html' => $translation['content_html'], 'content_plain' => $translation['content_plain']]));
        }

        return $translations;
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
    private function fetchDeLanguageId(Connection $connection): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'de-DE']) ?: null;
    }

    /**
     * @throws Exception
     */
    private function fetchEnLanguageId(Connection $connection): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'en-GB']) ?: null;
    }
}
