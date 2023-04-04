<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\EventLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be internal - reason:visibility-change
 *
 * @extends EntityCollection<WebhookEventLogEntity>
 */
#[Package('core')]
class WebhookEventLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WebhookEventLogEntity::class;
    }
}
