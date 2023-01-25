<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailSubjectUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1652345915UpdateSubjectMailTemplate extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1652345915;
    }

    public function update(Connection $connection): void
    {
        $mailSubjectUpdate = new MailSubjectUpdate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            'Your order with {{ salesChannel.name }} is shipped'
        );

        $this->updateEnMailSubject($connection, $mailSubjectUpdate);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
