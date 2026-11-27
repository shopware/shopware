<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Compiler;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Compiler\RemoveDevServices;
use Shopware\Core\Profiling\Controller\ProfilerController;
use Shopware\Core\Profiling\DataAbstractionLayer\DalCollector;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityAggregator;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityReader;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntitySearcher;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityWriter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RemoveDevServices::class)]
class RemoveDevServicesTest extends TestCase
{
    #[TestDox('removes the ProfilerController when there is no profiler service')]
    public function testRemovesProfilerControllerWithoutProfilerService(): void
    {
        $container = new ContainerBuilder();
        $container->register(ProfilerController::class);

        (new RemoveDevServices())->process($container);

        static::assertFalse($container->hasDefinition(ProfilerController::class));
    }

    #[TestDox('removes the DAL collector and its decorators when there is no profiler service')]
    public function testRemovesDalProfilingWithoutProfilerService(): void
    {
        $container = new ContainerBuilder();
        $container->register(DalCollector::class);
        $container->register(ProfilingEntityReader::class);
        $container->register(ProfilingEntitySearcher::class);
        $container->register(ProfilingEntityAggregator::class);
        $container->register(ProfilingEntityWriter::class);

        (new RemoveDevServices())->process($container);

        static::assertFalse($container->hasDefinition(DalCollector::class));
        static::assertFalse($container->hasDefinition(ProfilingEntityReader::class));
        static::assertFalse($container->hasDefinition(ProfilingEntitySearcher::class));
        static::assertFalse($container->hasDefinition(ProfilingEntityAggregator::class));
        static::assertFalse($container->hasDefinition(ProfilingEntityWriter::class));
    }

    #[TestDox('keeps the DAL collector and its decorators when the profiler service exists')]
    public function testKeepsDalProfilingWhenProfilerServicePresent(): void
    {
        $container = new ContainerBuilder();
        $container->register('profiler');
        $container->register(DalCollector::class);
        $container->register(ProfilingEntityReader::class);
        $container->register(ProfilingEntitySearcher::class);
        $container->register(ProfilingEntityAggregator::class);
        $container->register(ProfilingEntityWriter::class);

        (new RemoveDevServices())->process($container);

        static::assertTrue($container->hasDefinition(DalCollector::class));
        static::assertTrue($container->hasDefinition(ProfilingEntityReader::class));
        static::assertTrue($container->hasDefinition(ProfilingEntitySearcher::class));
        static::assertTrue($container->hasDefinition(ProfilingEntityAggregator::class));
        static::assertTrue($container->hasDefinition(ProfilingEntityWriter::class));
    }

    #[TestDox('keeps the ProfilerController when the profiler service exists and the web profiler bundle is installed')]
    public function testKeepsProfilerControllerWhenProfilerServicePresent(): void
    {
        if (!InstalledVersions::isInstalled('symfony/web-profiler-bundle')) {
            static::markTestSkipped('symfony/web-profiler-bundle is not installed in this environment');
        }

        $container = new ContainerBuilder();
        $container->register('profiler');
        $container->register(ProfilerController::class);

        (new RemoveDevServices())->process($container);

        static::assertTrue($container->hasDefinition(ProfilerController::class));
    }
}
