<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\V6_7\Migration1790751498ReadGaranLabelFromMailTemplateData;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1790751498ReadGaranLabelFromMailTemplateData::class)]
class Migration1790751498ReadGaranLabelFromMailTemplateDataTest extends MailTemplateMigrationTestCase
{
    private const FILTER_HTML = '{% set garanLabel = nestedItem.productId|sw_garan_label_mail(context) %}';

    private const FILTER_PLAIN = '{% set garanLabel = lineItem.productId|sw_garan_label_mail(context) %}';

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790751498, (new Migration1790751498ReadGaranLabelFromMailTemplateData())->getCreationTimestamp());
    }

    public function testMigrationReplacesTheFilterWithTheTemplateData(): void
    {
        $this->givenTheStoredTemplateUsesTheFilter(editedByMerchant: false);

        $migration = new Migration1790751498ReadGaranLabelFromMailTemplateData();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $expected = new MailUpdate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        $expected->loadByDirectoryName('order_confirmation_mail');

        $translations = $this->getMailTemplateTranslations(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM)->translations;

        static::assertSame($expected->getEnPlain(), $translations->getEnPlain());
        static::assertSame($expected->getEnHtml(), $translations->getEnHtml());
        static::assertSame($expected->getDePlain(), $translations->getDePlain());
        static::assertSame($expected->getDeHtml(), $translations->getDeHtml());

        foreach ([$translations->getEnHtml(), $translations->getEnPlain(), $translations->getDeHtml(), $translations->getDePlain()] as $content) {
            static::assertIsString($content);
            static::assertStringContainsString('garanLabels[', $content);
            static::assertStringNotContainsString('sw_garan_label_mail', $content);
        }
    }

    public function testMigrationKeepsTemplatesEditedByTheMerchant(): void
    {
        $this->givenTheStoredTemplateUsesTheFilter(editedByMerchant: true);

        (new Migration1790751498ReadGaranLabelFromMailTemplateData())->update($this->connection);

        $translations = $this->getMailTemplateTranslations(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM)->translations;

        static::assertSame(self::FILTER_HTML, $translations->getEnHtml());
        static::assertSame(self::FILTER_PLAIN, $translations->getDePlain());
    }

    private function givenTheStoredTemplateUsesTheFilter(bool $editedByMerchant): void
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
                'contentHtml' => self::FILTER_HTML,
                'contentPlain' => self::FILTER_PLAIN,
                'technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            ]
        );
    }
}
