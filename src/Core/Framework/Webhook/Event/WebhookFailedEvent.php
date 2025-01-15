<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('core')]
class WebhookFailedEvent extends Event
{
    public function __construct(
        public readonly WebhookEventMessage $message,
        public readonly \Throwable $exception,
        public readonly int $numFails
    ) {
    }
}
