<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1793904418UpdateMailTemplatesForMultipleShipments extends MigrationStep
{
    use UpdateMailTrait;

    private const MAIL_TEMPLATE_TYPES = [
        MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
        MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
    ];

    public function getCreationTimestamp(): int
    {
        return 1793904418;
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
