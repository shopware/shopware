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
class Migration1764580028AddAltAttributeToOrderConfirmationMailImages extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1764580028;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $orderConfirmUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        $orderConfirmUpdate->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'));
        $orderConfirmUpdate->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'));
        $orderConfirmUpdate->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-plain.html.twig'));
        $orderConfirmUpdate->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_confirmation_mail/de-html.html.twig'));
        $this->updateMail($orderConfirmUpdate, $connection);

        $paidUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID);
        $paidUpdate->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig'));
        $paidUpdate->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig'));
        $paidUpdate->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-plain.html.twig'));
        $paidUpdate->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.paid/de-html.html.twig'));
        $this->updateMail($paidUpdate, $connection);

        $cancelledUpdate = new MailUpdate(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED);
        $cancelledUpdate->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig'));
        $cancelledUpdate->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig'));
        $cancelledUpdate->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig'));
        $cancelledUpdate->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig'));
        $this->updateMail($cancelledUpdate, $connection);
    }
}
