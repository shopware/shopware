<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Tracking;

use Shopware\Core\Framework\Log\Package;

/**
 * Fire-and-forget sink for anonymous usage events.
 *
 * @internal
 */
#[Package('framework')]
interface TrackingTransport
{
    public function send(string $payload): void;
}
