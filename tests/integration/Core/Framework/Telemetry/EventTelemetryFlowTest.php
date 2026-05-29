<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Telemetry;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\Subscriber\TelemetryFlushListener;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;
use Shopware\Core\Framework\Test\TestKernel;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Tests\Integration\Core\Framework\Trait\CustomKernelTestBehavior;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * Tests against sample Metrics that the general flow is working correctly.
 * Having this tests pass does not guarantee that ALL the metrics are working correctly.
 * Rather a sanity check that the telemetry system is working as intended.
 */
#[Package('framework')]
#[Group('slow')]
class EventTelemetryFlowTest extends TestCase
{
    /** @use CustomKernelTestBehavior<TelemetryEnabledTestKernel> */
    use CustomKernelTestBehavior;

    private const TELEMETRY_ENABLED_FIXTURE = __DIR__ . '/_fixtures/telemetry_enabled.yaml';

    private static bool $hadTelemetryMetricsFeatureFlag = false;

    private static mixed $originalTelemetryMetricsFeatureFlag = null;

    private TraceableTransport $transport;

    public static function setUpBeforeClass(): void
    {
        self::enableTelemetryMetricsFeatureFlag();
        self::loadKernel();
    }

    public static function tearDownAfterClass(): void
    {
        self::unloadKernel();
        self::restoreTelemetryMetricsFeatureFlag();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $transportsCollection = self::getContainer()->get(TransportCollection::class);
        static::assertInstanceOf(TransportCollection::class, $transportsCollection);
        $transport = current(iterator_to_array($transportsCollection->getIterator()));
        static::assertInstanceOf(TraceableTransport::class, $transport);
        $this->transport = $transport;

        $this->transport->reset();
    }

    public function testCacheInvalidateMetricEmitted(): void
    {
        $cacheInvalidator = self::getContainer()->get(CacheInvalidator::class);
        static::assertInstanceOf(CacheInvalidator::class, $cacheInvalidator);
        $cacheInvalidator->invalidate(['test-tag']);

        $metricConfig = MetricConfig::fromDefinition('cache.invalidate.count', [
            'type' => Type::COUNTER->value,
            'description' => 'Number of cache invalidations',
            'enabled' => true,
        ]);
        static::assertEquals(
            Metric::fromConfigured(new ConfiguredMetric('cache.invalidate.count', 1), $metricConfig, []),
            $this->getEmittedMetricByName('cache.invalidate.count')
        );
    }

    public function testDalAssociationsCountEmitted(): void
    {
        $userRepository = self::getContainer()->get('user.repository');
        $criteria = new Criteria();
        $criteria->addAssociations(['aclRoles', 'avatarMedia']);

        $metricConfig = MetricConfig::fromDefinition('dal.associations.count', [
            'type' => Type::HISTOGRAM->value,
            'description' => 'Number of associations loaded',
            'enabled' => true,
        ]);

        // search triggers EntitySearchedEvent, event is configured via attribute
        $userRepository->search($criteria, Context::createDefaultContext())->first();
        static::assertEquals(
            Metric::fromConfigured(new ConfiguredMetric('dal.associations.count', 2), $metricConfig, []),
            $this->getEmittedMetricByName('dal.associations.count')
        );
    }

    public function testFlushListenerIsRegisteredOnKernelTerminate(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $matchingListeners = array_filter(
            $dispatcher->getListeners(KernelEvents::TERMINATE),
            static fn (mixed $listener): bool => \is_array($listener)
                && ($listener[0] ?? null) instanceof TelemetryFlushListener
        );

        static::assertNotEmpty(
            $matchingListeners,
            \sprintf(
                'TelemetryFlushListener must be subscribed to "%s" so push transports get a chance to flush '
                . 'batched emissions at the end of the request lifecycle. If this fails, the listener '
                . 'is no longer wired up via DI or the "kernel.event_subscriber" tag was removed.',
                KernelEvents::TERMINATE,
            ),
        );
    }

    private function getEmittedMetricByName(string $name): Metric
    {
        foreach ($this->transport->getEmittedMetrics() as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        static::fail(\sprintf('Expected metric "%s" was not emitted.', $name));
    }

    /**
     * @return class-string<TelemetryEnabledTestKernel>
     */
    private static function getKernelClass(): string
    {
        return TelemetryEnabledTestKernel::class;
    }

    private static function getKernelCacheId(): string
    {
        return Hasher::hashFile(self::TELEMETRY_ENABLED_FIXTURE);
    }

    private static function enableTelemetryMetricsFeatureFlag(): void
    {
        self::$hadTelemetryMetricsFeatureFlag = \array_key_exists('TELEMETRY_METRICS', $_SERVER);
        self::$originalTelemetryMetricsFeatureFlag = $_SERVER['TELEMETRY_METRICS'] ?? null;

        $_SERVER['TELEMETRY_METRICS'] = '1';
    }

    private static function restoreTelemetryMetricsFeatureFlag(): void
    {
        if (self::$hadTelemetryMetricsFeatureFlag) {
            $_SERVER['TELEMETRY_METRICS'] = self::$originalTelemetryMetricsFeatureFlag;

            return;
        }

        unset($_SERVER['TELEMETRY_METRICS']);
    }
}

/**
 * @internal
 */
class TelemetryEnabledTestKernel extends TestKernel
{
    public function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $loader->load(__DIR__ . '/_fixtures/telemetry_enabled.yaml');
    }
}
