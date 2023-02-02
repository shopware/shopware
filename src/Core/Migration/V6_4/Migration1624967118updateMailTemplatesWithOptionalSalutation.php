<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1624967118updateMailTemplatesWithOptionalSalutation extends MigrationStep
{
    use UpdateMailTrait;

    final public const MAIL_TYPE_DIRS = [
        'order_confirmation_mail',
        'order_delivery.state.cancelled',
        'order_delivery.state.returned',
        'customer.group.registration.accepted',
        'customer.group.registration.declined',
        'guest_order.double_opt_in',
        'order_delivery.state.returned_partially',
        'order_delivery.state.shipped',
        'order_delivery.state.shipped_partially',
        'order.state.cancelled',
        'order.state.completed',
        'order.state.in_progress',
        'order.state.open',
        'order_transaction.state.cancelled',
        'order_transaction.state.open',
        'order_transaction.state.paid',
        'order_transaction.state.paid_partially',
        'order_transaction.state.refunded',
        'order_transaction.state.refunded_partially',
        'order_transaction.state.reminded',
        'cancellation_mail',
        'credit_note_mail',
        'delivery_mail',
        'invoice_mail',
    ];

    public function getCreationTimestamp(): int
    {
        return 1624967118;
    }

    public function update(Connection $connection): void
    {
        foreach (self::getUpdates() as $update) {
            $this->updateMail($update, $connection);
        }
    }

    /**
     * @return array<MailUpdate>
     */
    public static function getUpdates(): array
    {
        return \array_map(static fn (string $mailTypeDirectory): MailUpdate => new MailUpdate(
            $mailTypeDirectory,
            (string) \file_get_contents(\sprintf('%s/../Fixtures/mails/%s/en-plain.html.twig', __DIR__, $mailTypeDirectory)),
            (string) \file_get_contents(\sprintf('%s/../Fixtures/mails/%s/en-html.html.twig', __DIR__, $mailTypeDirectory)),
            (string) \file_get_contents(\sprintf('%s/../Fixtures/mails/%s/de-plain.html.twig', __DIR__, $mailTypeDirectory)),
            (string) \file_get_contents(\sprintf('%s/../Fixtures/mails/%s/de-html.html.twig', __DIR__, $mailTypeDirectory))
        ), self::MAIL_TYPE_DIRS);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
