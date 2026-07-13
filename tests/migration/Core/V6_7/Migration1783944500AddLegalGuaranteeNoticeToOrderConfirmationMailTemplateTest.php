<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\V6_7\Migration1783944500AddLegalGuaranteeNoticeToOrderConfirmationMailTemplate;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1783944500AddLegalGuaranteeNoticeToOrderConfirmationMailTemplate::class)]
class Migration1783944500AddLegalGuaranteeNoticeToOrderConfirmationMailTemplateTest extends MailTemplateMigrationTestCase
{
    private const MAIL_TEMPLATE_TYPE = 'order_confirmation_mail';

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1783944500, (new Migration1783944500AddLegalGuaranteeNoticeToOrderConfirmationMailTemplate())->getCreationTimestamp());
    }

    public function testMigrationRunsTwiceWithoutError(): void
    {
        $this->expectNotToPerformAssertions();

        $migration = new Migration1783944500AddLegalGuaranteeNoticeToOrderConfirmationMailTemplate();

        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    public function testMigrationAddsLegalGuaranteeNoticeToTemplateContent(): void
    {
        $migration = new Migration1783944500AddLegalGuaranteeNoticeToOrderConfirmationMailTemplate();
        $migration->update($this->connection);

        $translations = $this->getMailTemplateTranslations(self::MAIL_TEMPLATE_TYPE)->translations;

        static::assertStringContainsString('sw_legal_guarantee_notice_link', (string) $translations->getEnHtml());
        static::assertStringContainsString('sw_legal_guarantee_notice_link', (string) $translations->getEnPlain());
        static::assertStringContainsString('sw_legal_guarantee_notice_link', (string) $translations->getDeHtml());
        static::assertStringContainsString('sw_legal_guarantee_notice_link', (string) $translations->getDePlain());
        static::assertStringContainsString('config(\'core.cart.showLegalGuaranteeNotice\')', (string) $translations->getEnHtml());
    }
}
