<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Compiler;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Command\DebugProfileCommand;
use Shopware\Core\Profiling\Compiler\RemoveDevServices;
use Shopware\Core\Profiling\Controller\ProfilerController;
use Shopware\Core\Profiling\Export\ProfileExporter;
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

    #[TestDox('removes the profile export command when there is no profiler service')]
    public function testRemovesProfileExportWithoutProfilerService(): void
    {
        $container = new ContainerBuilder();
        $container->register(ProfileExporter::class);
        $container->register(DebugProfileCommand::class);

        (new RemoveDevServices())->process($container);

        static::assertFalse($container->hasDefinition(ProfileExporter::class));
        static::assertFalse($container->hasDefinition(DebugProfileCommand::class));
    }

    #[TestDox('keeps the profile export command when the profiler service exists')]
    public function testKeepsProfileExportWithProfilerService(): void
    {
        $container = new ContainerBuilder();
        $container->register('profiler');
        $container->register(ProfileExporter::class);
        $container->register(DebugProfileCommand::class);

        (new RemoveDevServices())->process($container);

        static::assertTrue($container->hasDefinition(ProfileExporter::class));
        static::assertTrue($container->hasDefinition(DebugProfileCommand::class));
    }
}
