<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface PartitionAwareHookable extends Hookable
{
    /**
     * Returns the partition key for this event.
     * Events with the same partition key are delivered in best-effort order
     * within the same app (app_name is always prefixed internally).
     * Failures can cause skips and out-of-order delivery.
     *
     * @return string|null Partition key, or null to use the default (app_name + webhook_id).
     */
    public function getPartitionKey(): ?string;
}
