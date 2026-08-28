<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Tracking\TrackingService;
use Shopware\Core\Framework\Telemetry\Tracking\TrackingTransport;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TrackingService::class)]
class TrackingServiceTest extends TestCase
{
    /**
     * @var array{server: mixed, env: mixed}
     */
    private array $previousDoNotTrack;

    protected function setUp(): void
    {
        $this->previousDoNotTrack = [
            'server' => $_SERVER['DO_NOT_TRACK'] ?? false,
            'env' => $_ENV['DO_NOT_TRACK'] ?? false,
        ];

        unset($_SERVER['DO_NOT_TRACK'], $_ENV['DO_NOT_TRACK']);
    }

    protected function tearDown(): void
    {
        if ($this->previousDoNotTrack['server'] === false) {
            unset($_SERVER['DO_NOT_TRACK']);
        } else {
            $_SERVER['DO_NOT_TRACK'] = $this->previousDoNotTrack['server'];
        }

        if ($this->previousDoNotTrack['env'] === false) {
            unset($_ENV['DO_NOT_TRACK']);
        } else {
            $_ENV['DO_NOT_TRACK'] = $this->previousDoNotTrack['env'];
        }
    }

    public function testTrackGeneratesAndPersistsIdWhenNotSet(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn(null);
        $systemConfig->expects($this->once())
            ->method('set')
            ->with(
                TrackingService::TELEMETRY_ID,
                static::callback(static function (string $id): bool {
                    return (bool) preg_match('/^[a-f0-9]{32}$/', $id);
                })
            );

        $transport = new RecordingTrackingTransport();
        $this->createService($systemConfig, $transport)->track('plugin.create');

        static::assertCount(1, $transport->payloads);

        $payload = json_decode($transport->payloads[0], true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload);
        static::assertSame('shopware.plugin.create', $payload['event']);
        static::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $payload['user_id']);
        static::assertSame('6.7.15.0', $payload['tags']['shopware_version']);
        static::assertSame(\PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION, $payload['tags']['php_version']);
        static::assertSame('2026-08-28T12:00:00+00:00', $payload['timestamp']);
    }

    public function testTrackUsesExistingIdFromConfig(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(static function (string $key): ?string {
            return $key === TrackingService::TELEMETRY_ID ? 'existing-id-123' : null;
        });
        $systemConfig->expects($this->never())->method('set');

        $transport = new RecordingTrackingTransport();
        $this->createService($systemConfig, $transport)->track('make.plugin', ['command' => 'make:plugin:entity']);

        $payload = json_decode($transport->payloads[0], true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload);
        static::assertSame('existing-id-123', $payload['user_id']);
        static::assertSame('make:plugin:entity', $payload['tags']['command']);
        static::assertSame('6.7.15.0', $payload['tags']['shopware_version']);
    }

    public function testTrackMigratesLegacyKey(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(static function (string $key): ?string {
            return $key === TrackingService::LEGACY_DEPLOYMENT_HELPER_ID ? 'legacy-id-456' : null;
        });
        $systemConfig->expects($this->once())
            ->method('set')
            ->with(TrackingService::TELEMETRY_ID, 'legacy-id-456');
        $systemConfig->expects($this->once())
            ->method('delete')
            ->with(TrackingService::LEGACY_DEPLOYMENT_HELPER_ID);

        $transport = new RecordingTrackingTransport();
        $this->createService($systemConfig, $transport)->track('plugin.create');

        $payload = json_decode($transport->payloads[0], true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload);
        static::assertSame('legacy-id-456', $payload['user_id']);
    }

    public function testTrackGeneratesEphemeralIdWhenConfigThrows(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willThrowException(new \RuntimeException('database unavailable'));
        $systemConfig->expects($this->never())->method('set');

        $transport = new RecordingTrackingTransport();
        $this->createService($systemConfig, $transport)->track('plugin.create');

        $payload = json_decode($transport->payloads[0], true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload);
        static::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $payload['user_id']);
    }

    public function testTrackIsSuppressedByDoNotTrack(): void
    {
        $_SERVER['DO_NOT_TRACK'] = '1';

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->never())->method('get');
        $systemConfig->expects($this->never())->method('set');

        $transport = new RecordingTrackingTransport();
        $this->createService($systemConfig, $transport)->track('plugin.create');

        static::assertSame([], $transport->payloads);
    }

    public function testPersistIdDoesNothingWhenNoIdGenerated(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->never())->method('set');

        $this->createService($systemConfig, new RecordingTrackingTransport())->persistId();
    }

    public function testPersistIdWritesGeneratedId(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willThrowException(new \RuntimeException('database unavailable'));
        $systemConfig->expects($this->once())
            ->method('set')
            ->with(
                TrackingService::TELEMETRY_ID,
                static::callback(static function (string $id): bool {
                    return (bool) preg_match('/^[a-f0-9]{32}$/', $id);
                })
            );

        $service = $this->createService($systemConfig, new RecordingTrackingTransport());
        $service->track('plugin.create');
        $service->persistId();
    }

    public function testShowHintPrintsTelemetryNotice(): void
    {
        $output = new BufferedOutput();
        $this->createService(
            static::createStub(SystemConfigService::class),
            new RecordingTrackingTransport()
        )->showHint($output);

        $content = $output->fetch();
        static::assertStringContainsString('Shopware collects anonymous telemetry', $content);
        static::assertStringContainsString(TrackingService::TELEMETRY_DOCS_URL, $content);
    }

    public function testShowHintIsSuppressedByDoNotTrack(): void
    {
        $_SERVER['DO_NOT_TRACK'] = '1';

        $output = new BufferedOutput();
        $this->createService(
            static::createStub(SystemConfigService::class),
            new RecordingTrackingTransport()
        )->showHint($output);

        static::assertSame('', $output->fetch());
    }

    private function createService(
        SystemConfigService $systemConfig,
        TrackingTransport $transport,
    ): TrackingService {
        /** @var ClockInterface&Stub $clock */
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-28T12:00:00+00:00'));

        return new TrackingService($systemConfig, $transport, $clock, '6.7.15.0');
    }
}

/**
 * @internal
 */
class RecordingTrackingTransport implements TrackingTransport
{
    /**
     * @var list<string>
     */
    public array $payloads = [];

    public function send(string $payload): void
    {
        $this->payloads[] = $payload;
    }
}
