<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Compiler;

use Composer\InstalledVersions;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Controller\ProfilerController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class RemoveDevServices implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!InstalledVersions::isInstalled('symfony/web-profiler-bundle') || !$container->hasDefinition('profiler')) {
            $container->removeDefinition(ProfilerController::class);
        }
    }
}
