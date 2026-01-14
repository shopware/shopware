<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\CancellationRequest\Event\CancellationRequestEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1764926297CancellationRequestFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764926297;
    }

    public function update(Connection $connection): void
    {
        $connection->insert(
            'flow',
            [
                'id' => Uuid::randomBytes(),
                'name' => 'Online Cancellation Request sent',
                'event_name' => CancellationRequestEvent::EVENT_NAME,
                'priority' => 1,
                'invalid' => 0,
                'active' => true,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
