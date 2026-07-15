<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\V6_7\Migration1783944800AddGaranLabelToOrderConfirmationMailTemplate;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1783944800AddGaranLabelToOrderConfirmationMailTemplate::class)]
class Migration1783944800AddGaranLabelToOrderConfirmationMailTemplateTest extends MailTemplateMigrationTestCase
{
    private const MAIL_TEMPLATE_TYPE = 'order_confirmation_mail';

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1783944800, (new Migration1783944800AddGaranLabelToOrderConfirmationMailTemplate())->getCreationTimestamp());
    }

    public function testMigrationRunsTwiceWithoutError(): void
    {
        $this->expectNotToPerformAssertions();

        $migration = new Migration1783944800AddGaranLabelToOrderConfirmationMailTemplate();

        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    public function testMigrationAddsGaranLabelToTemplateContent(): void
    {
        $migration = new Migration1783944800AddGaranLabelToOrderConfirmationMailTemplate();
        $migration->update($this->connection);

        $translations = $this->getMailTemplateTranslations(self::MAIL_TEMPLATE_TYPE)->translations;

        static::assertStringContainsString('sw_garan_label', (string) $translations->getEnHtml());
        static::assertStringContainsString('sw_garan_label', (string) $translations->getDeHtml());
        static::assertStringContainsString('config(\'core.cart.showGaranLabelInOrderConfirmation\')', (string) $translations->getEnHtml());
    }
}
