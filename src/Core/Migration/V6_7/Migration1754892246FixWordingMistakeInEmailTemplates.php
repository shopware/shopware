<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1754892246FixWordingMistakeInEmailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1754892246;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_mail/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_mail/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_mail/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/cancellation_mail/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/credit_note_mail/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/credit_note_mail/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/credit_note_mail/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/credit_note_mail/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/delivery_mail/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/delivery_mail/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/delivery_mail/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/delivery_mail/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/invoice_mail/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/invoice_mail/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/invoice_mail/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/invoice_mail/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.cancelled/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.completed/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.completed/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.completed/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.completed/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.in_progress/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.in_progress/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.in_progress/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.in_progress/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.open/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.open/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.open/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order.state.open/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.cancelled/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.returned_partially/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_delivery.state.shipped_partially/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.open/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid_partially/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.refunded_partially/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.reminded/de-html.html.twig'));
        $this->updateMail($update, $connection);

        $update = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-html.html.twig'));
        $this->updateMail($update, $connection);
    }
}
