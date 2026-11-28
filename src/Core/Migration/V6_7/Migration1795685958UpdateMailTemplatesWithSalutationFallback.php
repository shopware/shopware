<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1795685958UpdateMailTemplatesWithSalutationFallback extends MigrationStep
{
    use UpdateMailTrait;

    private const MAIL_TEMPLATE_TYPES = [
        MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE,
        MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE,
        MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_ACCEPTED,
        MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_REGISTRATION_DECLINED,
        CustomerPasswordChangedEvent::EVENT_NAME,
        MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE,
        MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY,
        MailTemplateTypes::MAILTYPE_GUEST_ORDER_DOUBLE_OPT_IN,
        MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE,
        MailTemplateTypes::MAILTYPE_ORDER_PAYMENT_METHOD_CHANGED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
        MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED,
        MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE,
    ];

    public function getCreationTimestamp(): int
    {
        return 1795685958;
    }

    public function update(Connection $connection): void
    {
        foreach (self::MAIL_TEMPLATE_TYPES as $mailTemplateType) {
            $update = new MailUpdate($mailTemplateType);
            $update->loadByDirectoryName($mailTemplateType);

            $this->updateMail($update, $connection);
        }
    }
}
