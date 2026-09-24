<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\V6_7\Migration1790078381EmbedGaranLabelInOrderConfirmationMail;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1790078381EmbedGaranLabelInOrderConfirmationMail::class)]
class Migration1790078381EmbedGaranLabelInOrderConfirmationMailTest extends MailTemplateMigrationTestCase
{
    private const DATA_URI_HTML = '<img src="{{ garanLabelDataUri }}" width="195" height="30" alt="GARAN label"/>';

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790078381, (new Migration1790078381EmbedGaranLabelInOrderConfirmationMail())->getCreationTimestamp());
    }

    public function testMigrationReappliesOrderConfirmationMailTemplate(): void
    {
        $this->givenTheStoredTemplateUsesTheDataUri(editedByMerchant: false);

        $migration = new Migration1790078381EmbedGaranLabelInOrderConfirmationMail();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $expected = new MailUpdate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        $expected->loadByDirectoryName('order_confirmation_mail');

        $translations = $this->getMailTemplateTranslations(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM)->translations;

        static::assertSame($expected->getEnPlain(), $translations->getEnPlain());
        static::assertSame($expected->getEnHtml(), $translations->getEnHtml());
        static::assertSame($expected->getDePlain(), $translations->getDePlain());
        static::assertSame($expected->getDeHtml(), $translations->getDeHtml());
    }

    public function testMigratedTemplateEmbedsTheLabelAsInlineImageAndNamesTheDuration(): void
    {
        $this->givenTheStoredTemplateUsesTheDataUri(editedByMerchant: false);

        (new Migration1790078381EmbedGaranLabelInOrderConfirmationMail())->update($this->connection);

        $translations = $this->getMailTemplateTranslations(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM)->translations;

        foreach (['en' => $translations->getEnHtml(), 'de' => $translations->getDeHtml()] as $language => $html) {
            static::assertIsString($html);
            static::assertStringContainsString('sw_garan_label_mail', $html, $language . ': the mail references the label via cid');
            static::assertStringNotContainsString('sw_garan_label_nested_uri', $html, $language);
            static::assertStringContainsString('alt="GARAN', $html, $language);
            static::assertStringContainsString('{{ garanLabel.duration', $html, $language . ': the alt text has to name the duration');
            static::assertMatchesRegularExpression(
                '/\{% if garanLabel\.cid %\}.*<img.*\{% else %\}.*\{\{ garanLabel\.duration.*\{% endif %\}/s',
                $html,
                $language . ': durations without an image fall back to the duration text'
            );
        }

        foreach (['en' => $translations->getEnPlain(), 'de' => $translations->getDePlain()] as $language => $plain) {
            static::assertIsString($plain);
            static::assertStringContainsString('sw_garan_label_mail', $plain, $language . ': the plain text mail has to name the guarantee as well');
            static::assertStringContainsString('{{ garanLabel.duration', $plain, $language);
        }
    }

    public function testMigrationKeepsTemplatesEditedByTheMerchant(): void
    {
        $this->givenTheStoredTemplateUsesTheDataUri(editedByMerchant: true);

        (new Migration1790078381EmbedGaranLabelInOrderConfirmationMail())->update($this->connection);

        $translations = $this->getMailTemplateTranslations(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM)->translations;

        static::assertSame(self::DATA_URI_HTML, $translations->getEnHtml());
        static::assertSame(self::DATA_URI_HTML, $translations->getDeHtml());
    }

    private function givenTheStoredTemplateUsesTheDataUri(bool $editedByMerchant): void
    {
        $this->connection->executeStatement(
            'UPDATE `mail_template` AS `template`
             INNER JOIN `mail_template_type` AS `type` ON `template`.`mail_template_type_id` = `type`.`id`
             SET `template`.`updated_at` = NULL
             WHERE `type`.`technical_name` = :technicalName',
            ['technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]
        );

        $this->connection->executeStatement(
            'UPDATE `mail_template_translation` AS `translation`
             INNER JOIN `mail_template` AS `template` ON `translation`.`mail_template_id` = `template`.`id`
             INNER JOIN `mail_template_type` AS `type` ON `template`.`mail_template_type_id` = `type`.`id`
             SET `translation`.`updated_at` = :updatedAt,
                 `translation`.`content_html` = :contentHtml,
                 `translation`.`content_plain` = :contentPlain
             WHERE `type`.`technical_name` = :technicalName',
            [
                'updatedAt' => $editedByMerchant ? '2026-01-01 00:00:00.000' : null,
                'contentHtml' => self::DATA_URI_HTML,
                'contentPlain' => 'OUTDATED-plain',
                'technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            ]
        );
    }
}
