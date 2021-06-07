<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\EventLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal (FEATURE_NEXT_14363)
 *
 * @method void                       add(WebhookEventLogEntity $entity)
 * @method void                       set(string $key, WebhookEventLogEntity $entity)
 * @method WebhookEventLogEntity[]    getIterator()
 * @method WebhookEventLogEntity[]    getElements()
 * @method WebhookEventLogEntity|null get(string $key)
 * @method WebhookEventLogEntity|null first()
 * @method WebhookEventLogEntity|null last()
 */
class WebhookEventLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WebhookEventLogEntity::class;
    }
}
