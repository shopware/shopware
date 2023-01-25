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
class Migration1610621999UpdateDateOfDefaultMailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1610621999;
    }

    public function update(Connection $connection): void
    {
        // update DELIVERY_STATE_SHIPPED_PARTIALLY
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY, $connection);

        // update DELIVERY_STATE_RETURNED_PARTIALLY
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY, $connection);

        // update DELIVERY_STATE_RETURNED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED, $connection);

        // update DELIVERY_STATE_CANCELLED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED, $connection);

        // update DELIVERY_STATE_SHIPPED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED, $connection);

        // update ORDER_STATE_OPEN
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN, $connection);

        // update ORDER_STATE_IN_PROGRESS
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS, $connection);

        // update ORDER_STATE_COMPLETED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED, $connection);

        // update ORDER_STATE_CANCELLED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED, $connection);

        // update TRANSACTION_STATE_REFUNDED_PARTIALLY
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY, $connection);

        // update TRANSACTION_STATE_REMINDED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED, $connection);

        // update TRANSACTION_STATE_OPEN
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN, $connection);

        // update TRANSACTION_STATE_PAID | updated in other place

        // update TRANSACTION_STATE_CANCELLED | updated in other place

        // update TRANSACTION_STATE_REFUNDED
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED, $connection);

        // update TRANSACTION_STATE_PAID_PARTIALLY
        $this->updateMailTemplatesByType(MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateMailTemplatesByType(string $type, Connection $connection): void
    {
        $update = new MailUpdate(
            $type,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/' . $type . '/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/' . $type . '/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/' . $type . '/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/' . $type . '/de-html.html.twig')
        );

        $this->updateMail($update, $connection);
    }
}
