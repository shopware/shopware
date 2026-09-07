<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\FeatureFlag;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\SavedConfig;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber\TestSkippedSubscriber;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(TestSkippedSubscriber::class)]
class TestSkippedSubscriberTest extends TestCase
{
    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $originalFeatures = [];

    /**
     * @var array<mixed>
     */
    private array $originalServerVars = [];

    protected function setUp(): void
    {
        $this->originalFeatures = Feature::getRegisteredFeatures();
        $this->originalServerVars = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServerVars;
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->originalFeatures);
    }

    public function testNotifyRestoresTheSavedFeatureConfig(): void
    {
        $savedConfig = new SavedConfig();
        $savedConfig->savedFeatureConfig = ['RESTORED_FLAG' => ['default' => true]];
        $savedConfig->savedServerVars = [...$_SERVER, 'RESTORED_MARKER' => '1'];

        (new TestSkippedSubscriber($savedConfig))->notify($this->buildEvent());

        static::assertSame('1', $_SERVER['RESTORED_MARKER']);
        static::assertArrayHasKey('RESTORED_FLAG', Feature::getRegisteredFeatures());
        static::assertNull($savedConfig->savedFeatureConfig);
    }

    public function testNotifyDoesNothingWithoutASavedConfig(): void
    {
        $savedConfig = new SavedConfig();
        $savedConfig->savedServerVars = [...$_SERVER, 'RESTORED_MARKER' => '1'];

        (new TestSkippedSubscriber($savedConfig))->notify($this->buildEvent());

        static::assertArrayNotHasKey('RESTORED_MARKER', $_SERVER);
    }

    private function buildEvent(): Skipped
    {
        $time = HRTime::fromSecondsAndNanoseconds(0, 0);
        $duration = Duration::fromSecondsAndNanoseconds(0, 0);
        $memory = MemoryUsage::fromBytes(0);
        $gc = new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0);
        $snap = new Snapshot($time, $memory, $memory, $gc);

        return new Skipped(new Info($snap, $duration, $memory, $duration, $memory), new Phpt('fakeFile'), 'skipped');
    }
}
