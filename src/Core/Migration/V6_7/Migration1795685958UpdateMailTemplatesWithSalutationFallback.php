<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
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
        'cancellation_mail',
        'credit_note_mail',
        'customer.group.registration.accepted',
        'customer.group.registration.declined',
        'customer.password.changed',
        'delivery_mail',
        'downloads_delivery',
        'guest_order.double_opt_in',
        'invoice_mail',
        'order.payment_method.changed',
        'order.state.cancelled',
        'order.state.completed',
        'order.state.in_progress',
        'order.state.open',
        'order_confirmation_mail',
        'order_delivery.state.cancelled',
        'order_delivery.state.returned',
        'order_delivery.state.returned_partially',
        'order_delivery.state.shipped',
        'order_delivery.state.shipped_partially',
        'order_transaction.state.authorized',
        'order_transaction.state.cancelled',
        'order_transaction.state.chargeback',
        'order_transaction.state.failed',
        'order_transaction.state.open',
        'order_transaction.state.paid',
        'order_transaction.state.paid_partially',
        'order_transaction.state.refunded',
        'order_transaction.state.refunded_partially',
        'order_transaction.state.reminded',
        'order_transaction.state.unconfirmed',
        'password_change',
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

    public function updateDestructive(Connection $connection): void
    {
    }
}
