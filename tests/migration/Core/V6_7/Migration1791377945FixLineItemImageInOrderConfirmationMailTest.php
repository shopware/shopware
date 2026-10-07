<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\V6_7\Migration1791377945FixLineItemImageInOrderConfirmationMail;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1791377945FixLineItemImageInOrderConfirmationMail::class)]
class Migration1791377945FixLineItemImageInOrderConfirmationMailTest extends MailTemplateMigrationTestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791377945, (new Migration1791377945FixLineItemImageInOrderConfirmationMail())->getCreationTimestamp());
    }

    public function testMigrationReappliesOrderConfirmationMailTemplate(): void
    {
        $migration = new Migration1791377945FixLineItemImageInOrderConfirmationMail();
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
}
