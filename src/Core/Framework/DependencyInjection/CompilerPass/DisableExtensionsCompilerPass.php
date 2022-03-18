<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\EmptyActiveAppsLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DisableExtensionsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!EnvironmentHelper::getVariable('DISABLE_EXTENSIONS', false)) {
            return;
        }

        $container->set(ActiveAppsLoader::class, new EmptyActiveAppsLoader());
    }
}
