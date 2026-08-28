<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Tracking\UdpTrackingTransport;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UdpTrackingTransport::class)]
class UdpTrackingTransportTest extends TestCase
{
    /**
     * @var array{server: mixed, env: mixed}
     */
    private array $previousTrackingDomain;

    protected function setUp(): void
    {
        $this->previousTrackingDomain = [
            'server' => $_SERVER['SHOPWARE_TRACKING_DOMAIN'] ?? false,
            'env' => $_ENV['SHOPWARE_TRACKING_DOMAIN'] ?? false,
        ];

        unset($_SERVER['SHOPWARE_TRACKING_DOMAIN'], $_ENV['SHOPWARE_TRACKING_DOMAIN']);
    }

    protected function tearDown(): void
    {
        if ($this->previousTrackingDomain['server'] === false) {
            unset($_SERVER['SHOPWARE_TRACKING_DOMAIN']);
        } else {
            $_SERVER['SHOPWARE_TRACKING_DOMAIN'] = $this->previousTrackingDomain['server'];
        }

        if ($this->previousTrackingDomain['env'] === false) {
            unset($_ENV['SHOPWARE_TRACKING_DOMAIN']);
        } else {
            $_ENV['SHOPWARE_TRACKING_DOMAIN'] = $this->previousTrackingDomain['env'];
        }
    }

    public function testSendDeliversPayloadToBoundUdpSocket(): void
    {
        if (!\function_exists('socket_create')) {
            static::markTestSkipped('ext-sockets is required');
        }

        $receiver = socket_create(\AF_INET, \SOCK_DGRAM, \SOL_UDP);
        static::assertInstanceOf(\Socket::class, $receiver);

        static::assertTrue(socket_bind($receiver, '127.0.0.1', 0));
        socket_getsockname($receiver, $address, $port);
        static::assertIsInt($port);
        static::assertGreaterThan(0, $port);

        socket_set_option($receiver, \SOL_SOCKET, \SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);

        $transport = new UdpTrackingTransport('127.0.0.1', $port);
        $transport->send('usage-event');

        $buffer = '';
        $from = '';
        $fromPort = 0;
        $bytes = @socket_recvfrom($receiver, $buffer, 1024, 0, $from, $fromPort);
        socket_close($receiver);

        static::assertNotFalse($bytes);
        static::assertSame('usage-event', $buffer);
    }

    #[DoesNotPerformAssertions]
    public function testRepeatedSendReusesSocketAndDoesNotThrow(): void
    {
        $transport = new UdpTrackingTransport('127.0.0.1', 9);
        $transport->send('first');
        $transport->send('second');
    }
}
