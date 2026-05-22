<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Compiler\RemoveDevServices;
use Shopware\Core\Profiling\Controller\ProfilerController;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RemoveDevServices::class)]
class RemoveDevServicesTest extends TestCase
{
    public function testKeepsProfilerControllerWhenProfilerServiceExists(): void
    {
        $container = new ContainerBuilder();
        $container->register('profiler', \stdClass::class);
        $container->register(ProfilerController::class, ProfilerController::class);

        (new RemoveDevServices())->process($container);

        static::assertTrue($container->hasDefinition(ProfilerController::class));
    }

    public function testRemovesProfilerControllerWhenProfilerServiceMissing(): void
    {
        $container = new ContainerBuilder();
        $container->register(ProfilerController::class, ProfilerController::class);

        (new RemoveDevServices())->process($container);

        static::assertFalse($container->hasDefinition(ProfilerController::class));
    }
}
