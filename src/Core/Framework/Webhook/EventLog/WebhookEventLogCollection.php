<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\EventLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<WebhookEventLogEntity>
 */
class WebhookEventLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WebhookEventLogEntity::class;
    }
}
