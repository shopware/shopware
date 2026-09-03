<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailSubjectUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1788180753FixPaymentReminderMailSubject extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1788180753;
    }

    public function update(Connection $connection): void
    {
        $this->updateMailSubject(
            new MailSubjectUpdate(
                MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
                'Payment reminder for your order with {{ salesChannel.translated.name }}',
                'Zahlungserinnerung für Ihre Bestellung bei {{ salesChannel.translated.name }}',
            ),
            $connection
        );
    }
}
