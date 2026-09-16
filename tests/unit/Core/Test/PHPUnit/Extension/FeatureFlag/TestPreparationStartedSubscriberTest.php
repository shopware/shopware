<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\FeatureFlag;

use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox as TestDoxAttribute;
use PHPUnit\Framework\TestCase;
use PHPUnit\Metadata\MetadataCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\SavedConfig;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber\TestPreparationStartedSubscriber;
use Shopware\Tests\Integration\Core\Test\PHPUnit\Extension\FeatureFlag\_fixtures\ClassLevelOffender;
use Shopware\Tests\Integration\Core\Test\PHPUnit\Extension\FeatureFlag\_fixtures\CleanFixture;
use Shopware\Tests\Integration\Core\Test\PHPUnit\Extension\FeatureFlag\_fixtures\MethodLevelOffender;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TestPreparationStartedSubscriber::class)]
class TestPreparationStartedSubscriberTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[TestDoxAttribute('An integration test carrying #[DisabledFeatures] fails loudly instead of silently running with the flag active')]
    #[DataProvider('offenderProvider')]
    public function testDisabledFeaturesInIntegrationNamespaceIsRejected(string $class): void
    {
        $subscriber = new TestPreparationStartedSubscriber(new SavedConfig());

        $this->expectExceptionObject(new \RuntimeException(\sprintf(
            '#[DisabledFeatures] on %s::testSomething has no effect in the integration suite. Feature state there comes from the job configuration: the default integration job runs with feature flags off, integration-major runs with FEATURE_ALL=major. Remove the attribute; if the test must not run under an active major flag, guard it with Feature::skipTestIfActive() instead.',
            $class
        )));

        $subscriber->notify($this->preparationStartedFor($class, 'testSomething'));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function offenderProvider(): iterable
    {
        yield 'attribute on the test method' => [MethodLevelOffender::class];
        yield 'attribute on the test class' => [ClassLevelOffender::class];
    }

    #[TestDoxAttribute('An integration test without #[DisabledFeatures] is left untouched')]
    public function testCleanIntegrationTestIsIgnored(): void
    {
        $savedConfig = new SavedConfig();
        $subscriber = new TestPreparationStartedSubscriber($savedConfig);

        $subscriber->notify($this->preparationStartedFor(CleanFixture::class, 'testSomething'));

        static::assertNull($savedConfig->savedFeatureConfig, 'a non-allowed namespace must not reach the flag-rewriting path');
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private function preparationStartedFor(string $class, string $method): PreparationStarted
    {
        $snapshot = new Snapshot(
            HRTime::fromSecondsAndNanoseconds(0, 0),
            MemoryUsage::fromBytes(0),
            MemoryUsage::fromBytes(0),
            new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0),
        );

        return new PreparationStarted(
            new Info(
                $snapshot,
                Duration::fromSecondsAndNanoseconds(0, 0),
                MemoryUsage::fromBytes(0),
                Duration::fromSecondsAndNanoseconds(0, 0),
                MemoryUsage::fromBytes(0),
            ),
            new TestMethod(
                $class,
                $method,
                __FILE__,
                1,
                new TestDox($class, $method, $method),
                MetadataCollection::fromArray([]),
                TestDataCollection::fromArray([]),
            ),
        );
    }
}
