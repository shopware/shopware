<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Message;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Marks a delivery for paused outbox persistence.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class HeldDeliveryStamp implements StampInterface
{
}
