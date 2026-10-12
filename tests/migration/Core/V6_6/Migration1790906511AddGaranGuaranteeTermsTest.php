<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\V6_6\Migration1790906511AddGaranGuaranteeTerms;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Not a `MailTemplateMigrationTestCase`: its transaction does not survive the DDL of this migration.
 *
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1790906511AddGaranGuaranteeTerms::class)]
class Migration1790906511AddGaranGuaranteeTermsTest extends TestCase
{
    private const FOREIGN_KEY = 'fk.product.guarantee_terms_media_id';

    private const FIXTURE_DIR = __DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_confirmation_mail/';

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
        if ($this->foreignKeyExists()) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.guarantee_terms_media_id`');
        }

        if ($this->connection->fetchOne('SHOW INDEX FROM `product` WHERE `Key_name` = :name', ['name' => self::FOREIGN_KEY])) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP INDEX `fk.product.guarantee_terms_media_id`');
        }

        foreach (['guarantee_terms_media_id', 'guarantee_terms_url', 'guaranteeTermsMedia'] as $column) {
            if ($this->getColumnType($column) !== false) {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `product` DROP COLUMN `%s`', $column));
            }
        }

        $migration = new Migration1790906511AddGaranGuaranteeTerms();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame('binary', $this->getColumnType('guarantee_terms_media_id'));
        static::assertSame('varchar', $this->getColumnType('guarantee_terms_url'));
        static::assertSame('binary', $this->getColumnType('guaranteeTermsMedia'));
        static::assertTrue($this->foreignKeyExists());
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

        $filesystem = new Filesystem();
        $expected = new MailUpdate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $filesystem->readFile(self::FIXTURE_DIR . 'en-plain.html.twig'),
            $filesystem->readFile(self::FIXTURE_DIR . 'en-html.html.twig'),
            $filesystem->readFile(self::FIXTURE_DIR . 'de-plain.html.twig'),
            $filesystem->readFile(self::FIXTURE_DIR . 'de-html.html.twig'),
        );

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

    private function getColumnType(string $column): string|false
    {
        return $this->connection->fetchOne(
            'SELECT `DATA_TYPE` FROM `information_schema`.`COLUMNS`
             WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = \'product\' AND `COLUMN_NAME` = :column',
            ['column' => $column]
        );
    }

    private function foreignKeyExists(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `information_schema`.`TABLE_CONSTRAINTS`
             WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = \'product\'
               AND `CONSTRAINT_TYPE` = \'FOREIGN KEY\' AND `CONSTRAINT_NAME` = :name',
            ['name' => self::FOREIGN_KEY]
        );
    }
}
