<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\V6_7\Migration1788531858FixGaranLabelInOrderConfirmationMail;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1788531858FixGaranLabelInOrderConfirmationMail::class)]
class Migration1788531858FixGaranLabelInOrderConfirmationMailTest extends MailTemplateMigrationTestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788531858, (new Migration1788531858FixGaranLabelInOrderConfirmationMail())->getCreationTimestamp());
    }

    public function testMigrationReappliesOrderConfirmationMailTemplate(): void
    {
        $this->givenTheStoredTemplateIsOutdated();

        $migration = new Migration1788531858FixGaranLabelInOrderConfirmationMail();
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

    public function testMigratedTemplateRendersTheGaranLabelWithFixedDimensionsInsideTheProductCell(): void
    {
        $this->givenTheStoredTemplateIsOutdated();

        (new Migration1788531858FixGaranLabelInOrderConfirmationMail())->update($this->connection);

        $translations = $this->getMailTemplateTranslations(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM)->translations;

        foreach (['en' => $translations->getEnHtml(), 'de' => $translations->getDeHtml()] as $language => $html) {
            static::assertIsString($html);
            static::assertStringContainsString('sw_garan_label_nested_uri', $html, $language);
            static::assertStringContainsString(
                '<img src="{{ garanLabelDataUri }}" width="195" height="30"',
                $html,
                $language . ': the label image needs explicit dimensions, mail clients scale an SVG data URI to the container otherwise'
            );
            static::assertStringNotContainsString(
                '<td colspan="6"><img src="{{ garanLabelDataUri }}"',
                $html,
                $language . ': the label belongs into the product cell, not into a full width row of its own'
            );
            static::assertStringNotContainsString(
                'src="{{ garanLabelDataUri }}" alt=""',
                $html,
                $language . ': the label carries legally required information and must not be marked as decorative'
            );
        }
    }

    /**
     * Seeds the broken markup that shops which already ran Migration1783944800AddGaranLabel carry, and
     * clears `updated_at` so the template counts as untouched by the merchant.
     */
    private function givenTheStoredTemplateIsOutdated(): void
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
             SET `translation`.`updated_at` = NULL,
                 `translation`.`content_html` = :contentHtml,
                 `translation`.`content_plain` = :contentPlain
             WHERE `type`.`technical_name` = :technicalName',
            [
                'contentHtml' => '<tr><td colspan="6"><img src="{{ garanLabelDataUri }}" alt="" /></td></tr>',
                'contentPlain' => 'OUTDATED-plain',
                'technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            ]
        );
    }
}
