<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\FeatureFlag;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\SavedConfig;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber\TestFinishedSubscriber;
use Shopware\Tests\Unit\Core\Test\PHPUnit\TelemetryInfoFactory;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(TestFinishedSubscriber::class)]
class TestFinishedSubscriberTest extends TestCase
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

        (new TestFinishedSubscriber($savedConfig))->notify($this->buildEvent());

        static::assertSame('1', $_SERVER['RESTORED_MARKER']);
        static::assertArrayHasKey('RESTORED_FLAG', Feature::getRegisteredFeatures());
        static::assertNull($savedConfig->savedFeatureConfig);
    }

    public function testNotifyDoesNothingWithoutASavedConfig(): void
    {
        $savedConfig = new SavedConfig();
        $savedConfig->savedServerVars = [...$_SERVER, 'RESTORED_MARKER' => '1'];

        (new TestFinishedSubscriber($savedConfig))->notify($this->buildEvent());

        static::assertArrayNotHasKey('RESTORED_MARKER', $_SERVER);
    }

    private function buildEvent(): Finished
    {
        return new Finished(TelemetryInfoFactory::create(), new Phpt('fakeFile'), 0);
    }
}
