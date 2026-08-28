<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Tracking;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * Sends usage events as UDP datagrams. Failures are swallowed so tracking never
 * affects the calling command.
 *
 * @internal
 */
#[Package('framework')]
class UdpTrackingTransport implements TrackingTransport
{
    public const DEFAULT_TRACKING_DOMAIN = 'udp.usage.shopware.io';

    public const DEFAULT_TRACKING_PORT = 9000;

    private \Socket|false|null $socket = null;

    private readonly string $host;

    public function __construct(
        ?string $host = null,
        private readonly int $port = self::DEFAULT_TRACKING_PORT,
    ) {
        $configured = $host ?? EnvironmentHelper::getVariable('SHOPWARE_TRACKING_DOMAIN', self::DEFAULT_TRACKING_DOMAIN);
        $this->host = \is_string($configured) && $configured !== '' ? $configured : self::DEFAULT_TRACKING_DOMAIN;
    }

    public function __destruct()
    {
        if ($this->socket instanceof \Socket) {
            socket_close($this->socket);
        }
    }

    public function send(string $payload): void
    {
        $socket = $this->socket();
        if ($socket === null) {
            return;
        }

        @socket_sendto($socket, $payload, \strlen($payload), 0, $this->host, $this->port);
    }

    private function socket(): ?\Socket
    {
        if ($this->socket instanceof \Socket) {
            return $this->socket;
        }

        if ($this->socket === false) {
            return null;
        }

        if (!\function_exists('socket_create')) {
            $this->socket = false;

            return null;
        }

        $socket = @socket_create(\AF_INET, \SOCK_DGRAM, \SOL_UDP);
        $this->socket = $socket === false ? false : $socket;

        return $this->socket instanceof \Socket ? $this->socket : null;
    }
}
