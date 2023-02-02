<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1604585230UpdateOrderMailsForCashRounding extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1604585230;
    }

    public function update(Connection $connection): void
    {
        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-html.html.twig')
        );

        $this->updateMail($update, $connection);

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig')
        );

        $this->updateMail($update, $connection);

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
