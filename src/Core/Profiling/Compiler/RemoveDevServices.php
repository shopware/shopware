<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Compiler;

use Composer\InstalledVersions;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Controller\ProfilerController;
use Shopware\Core\Profiling\DataAbstractionLayer\DalCollector;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityAggregator;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityReader;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntitySearcher;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityWriter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class RemoveDevServices implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('profiler')) {
            $container->removeDefinition(DalCollector::class);
            $container->removeDefinition(ProfilingEntityReader::class);
            $container->removeDefinition(ProfilingEntitySearcher::class);
            $container->removeDefinition(ProfilingEntityAggregator::class);
            $container->removeDefinition(ProfilingEntityWriter::class);
        }

        if (!InstalledVersions::isInstalled('symfony/web-profiler-bundle') || !$container->hasDefinition('profiler')) {
            $container->removeDefinition(ProfilerController::class);
        }
    }
}
