<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PreWebhooksDispatchEvent
{
    /**
     * @param list<Webhook> $webhooks
     */
    public function __construct(public array $webhooks)
    {
    }
}
