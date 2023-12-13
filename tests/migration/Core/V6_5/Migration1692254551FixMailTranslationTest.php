<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1692254551FixMailTranslation;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1692254551FixMailTranslation::class)]
class Migration1692254551FixMailTranslationTest extends TestCase
{
    use MigrationTestTrait;

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1692254551FixMailTranslation();
        static::assertSame(1692254551, $migration->getCreationTimestamp());
    }

    public function testMailTemplatesUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1692254551FixMailTranslation();
        $migration->update($connection);

        $deLangId = $this->fetchLanguageId($connection, 'de-DE');
        $enLandId = $this->fetchLanguageId($connection, 'en-GB');
        static::assertNotNull($deLangId);
        static::assertNotNull($enLandId);

        $templates = [
            [
                'id' => $this->fetchSystemMailTemplateIdFromType(
                    $connection,
                    MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED
                ),
                'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.authorized/de-html.html.twig'),
                'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.authorized/de-plain.html.twig'),
                'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.authorized/en-html.html.twig'),
                'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.authorized/en-plain.html.twig'),
            ],
            [
                'id' => $this->fetchSystemMailTemplateIdFromType(
                    $connection,
                    MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK
                ),
                'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.chargeback/de-html.html.twig'),
                'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.chargeback/de-plain.html.twig'),
                'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.chargeback/en-html.html.twig'),
                'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.chargeback/en-plain.html.twig'),
            ],
            [
                'id' => $this->fetchSystemMailTemplateIdFromType(
                    $connection,
                    MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED
                ),
                'htmlDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.unconfirmed/de-html.html.twig'),
                'plainDe' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.unconfirmed/de-plain.html.twig'),
                'htmlEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.unconfirmed/en-html.html.twig'),
                'plainEn' => (string) file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_transaction.state.unconfirmed/en-plain.html.twig'),
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

            $translations[] = array_merge($template, ['content_html' => $translation['content_html'], 'content_plain' => $translation['content_plain']]);
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
    private function fetchLanguageId(Connection $connection, string $code): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]) ?: null;
    }
}
