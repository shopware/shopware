<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\V6_7\Migration1779798444FixGuestOrderStatusMailTemplates;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1779798444FixGuestOrderStatusMailTemplates::class)]
class Migration1779798444FixGuestOrderStatusMailTemplatesTest extends MailTemplateMigrationTestCase
{
    private const MAIL_TEMPLATE_TYPES = [
        MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
    ];

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779798444, (new Migration1779798444FixGuestOrderStatusMailTemplates())->getCreationTimestamp());
    }

    public function testMigrationUpdatesGuestOrderStatusMailTemplates(): void
    {
        foreach (self::MAIL_TEMPLATE_TYPES as $mailTemplateType) {
            $mailTranslations = new MailUpdate(
                $mailTemplateType,
                'BEFORE-en-plain',
                'BEFORE-en-html',
                'BEFORE-de-plain',
                'BEFORE-de-html',
            );

            $this->updateMail($mailTranslations, $this->connection);
        }

        $migration = new Migration1779798444FixGuestOrderStatusMailTemplates();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::MAIL_TEMPLATE_TYPES as $mailTemplateType) {
            $expected = new MailUpdate($mailTemplateType);
            $expected->loadByDirectoryName($mailTemplateType);

            $translation = $this->getMailTemplateTranslations($mailTemplateType);

            static::assertSame($expected->getEnPlain(), $translation->translations->getEnPlain(), $mailTemplateType . ': en plain');
            static::assertSame($expected->getEnHtml(), $translation->translations->getEnHtml(), $mailTemplateType . ': en html');
            static::assertSame($expected->getDePlain(), $translation->translations->getDePlain(), $mailTemplateType . ': de plain');
            static::assertSame($expected->getDeHtml(), $translation->translations->getDeHtml(), $mailTemplateType . ': de html');
        }
    }
}
