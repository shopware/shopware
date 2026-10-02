<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\V6_7\Migration1790906511AddGaranGuaranteeTerms;

/**
 * Not a `MailTemplateMigrationTestCase`: its transaction does not survive the DDL of this migration.
 *
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1790906511AddGaranGuaranteeTerms::class)]
class Migration1790906511AddGaranGuaranteeTermsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790906511, (new Migration1790906511AddGaranGuaranteeTerms())->getCreationTimestamp());
    }

    public function testAddsTheTermsColumnsTheirForeignKeyAndTheInheritanceColumn(): void
    {
        if (TableHelper::foreignKeyExists($this->connection, 'product', 'fk.product.guarantee_terms_media_id')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.guarantee_terms_media_id`');
        }

        if (TableHelper::indexExists($this->connection, 'product', 'fk.product.guarantee_terms_media_id')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP INDEX `fk.product.guarantee_terms_media_id`');
        }

        foreach (['guarantee_terms_media_id', 'guarantee_terms_url', 'guaranteeTermsMedia'] as $column) {
            if (TableHelper::columnExists($this->connection, 'product', $column)) {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `product` DROP COLUMN `%s`', $column));
            }
        }

        $migration = new Migration1790906511AddGaranGuaranteeTerms();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame('binary', TableHelper::getColumnOfTable($this->connection, 'product', 'guarantee_terms_media_id')->type);
        static::assertSame('string', TableHelper::getColumnOfTable($this->connection, 'product', 'guarantee_terms_url')->type);
        static::assertSame('binary', TableHelper::getColumnOfTable($this->connection, 'product', 'guaranteeTermsMedia')->type);
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'product', 'fk.product.guarantee_terms_media_id'));
    }

    public function testLinksTheTermsInUneditedOrderConfirmationMails(): void
    {
        $this->connection->executeStatement(
            'UPDATE `mail_template_translation` AS `translation`
             INNER JOIN `mail_template` AS `template` ON `translation`.`mail_template_id` = `template`.`id`
             INNER JOIN `mail_template_type` AS `type` ON `template`.`mail_template_type_id` = `type`.`id`
             SET `template`.`updated_at` = NULL,
                 `translation`.`updated_at` = NULL,
                 `translation`.`content_html` = :previousContent,
                 `translation`.`content_plain` = :previousContent
             WHERE `type`.`technical_name` = :technicalName AND `template`.`system_default` = 1',
            [
                'previousContent' => '{% set garanLabel = garanLabels[lineItem.productId] ?? null %}',
                'technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            ]
        );

        (new Migration1790906511AddGaranGuaranteeTerms())->update($this->connection);

        $expected = new MailUpdate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        $expected->loadByDirectoryName('order_confirmation_mail');

        $contents = $this->connection->fetchAllAssociative(
            'SELECT `translation`.`content_html`, `translation`.`content_plain`
             FROM `mail_template_translation` AS `translation`
             INNER JOIN `mail_template` AS `template` ON `translation`.`mail_template_id` = `template`.`id`
             INNER JOIN `mail_template_type` AS `type` ON `template`.`mail_template_type_id` = `type`.`id`
             WHERE `type`.`technical_name` = :technicalName AND `template`.`system_default` = 1',
            ['technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]
        );

        static::assertNotEmpty($contents);

        foreach ($contents as $content) {
            static::assertIsString($content['content_html']);
            static::assertIsString($content['content_plain']);
            static::assertContains($content['content_html'], [$expected->getEnHtml(), $expected->getDeHtml()]);
            static::assertContains($content['content_plain'], [$expected->getEnPlain(), $expected->getDePlain()]);
            static::assertStringContainsString('garanLabel.termsUrl', $content['content_html']);
            static::assertStringContainsString('garanLabel.termsUrl', $content['content_plain']);
        }
    }
}
