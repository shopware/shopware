<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1635147952ShowShippingCostsInCartAnMailTemplates;

class Migration1635147952ShowShippingCostsInCartAnMailTemplatesTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider providerTestMailTemplateMigration
     */
    public function testMailTemplateMigration(string $mailTemplateType, string $fixtureFolder, string $subjectEn, string $subjectDe): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        /** @var string $enLangId */
        $enLangId = $this->fetchEnLanguageId($connection);

        static::assertNotNull($enLangId);

        $migration = new Migration1635147952ShowShippingCostsInCartAnMailTemplates();
        $migration->update($connection);

        /** @var string $mailTemplateId */
        $mailTemplateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);
        static::assertNotNull($mailTemplateId);

        $sqlString = 'SELECT `subject`, `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $templateTranslationEn = $connection->fetchAssoc($sqlString, [
            'langId' => $enLangId,
            'templateId' => $mailTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($templateTranslationEn)) {
            static::assertEquals($subjectEn, $templateTranslationEn['subject']);
            static::assertEquals((string) file_get_contents(__DIR__ . sprintf('/../Fixtures/mails/%s/en-html.html.twig', $fixtureFolder)), $templateTranslationEn['content_html']);
            static::assertEquals((string) file_get_contents(__DIR__ . sprintf('/../Fixtures/mails/%s/en-plain.html.twig', $fixtureFolder)), $templateTranslationEn['content_plain']);
        }

        $deLangId = $this->fetchDeLanguageId($connection);

        $templateTranslationDe = $connection->fetchAssoc($sqlString, [
            'langId' => $deLangId,
            'templateId' => $mailTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($templateTranslationDe)) {
            static::assertEquals($subjectDe, $templateTranslationDe['subject']);
            static::assertEquals((string) file_get_contents(__DIR__ . sprintf('/../Fixtures/mails/%s/de-html.html.twig', $fixtureFolder)), $templateTranslationDe['content_html']);
            static::assertEquals((string) file_get_contents(__DIR__ . sprintf('/../Fixtures/mails/%s/de-plain.html.twig', $fixtureFolder)), $templateTranslationDe['content_plain']);
        }
    }

    public function providerTestMailTemplateMigration(): array
    {
        return [
            [
                MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
                'order_confirmation_mail',
                'Order confirmation',
                'Bestellbestätigung',
            ],
            [
                MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
                'order_transaction.state.cancelled',
                'The payment for your order with {{ salesChannel.name }} is cancelled',
                'Die Zahlung für ihre Bestellung bei {{ salesChannel.name }} wurde storniert',
            ],
            [
                MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
                'order_transaction.state.paid',
                'Your order with {{ salesChannel.name }} is completly paid',
                'Deine Bestellung bei {{ salesChannel.name }} wurde komplett bezahlt',
            ],
        ];
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchColumn();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchColumn();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchEnLanguageId(Connection $connection): ?string
    {
        return $connection->fetchColumn('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'en-GB']) ?: null;
    }

    private function fetchDeLanguageId(Connection $connection): ?string
    {
        return $connection->fetchColumn('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'de-DE']) ?: null;
    }
}
