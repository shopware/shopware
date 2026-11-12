<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;

/**
 * Marks a flow event that must never be delivered to a webhook. Flow Builder keeps the event;
 * only webhook delivery is withdrawn.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NotHookable
{
}
