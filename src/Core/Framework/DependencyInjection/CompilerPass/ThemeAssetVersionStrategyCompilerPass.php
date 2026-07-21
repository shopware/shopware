<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class ThemeAssetVersionStrategyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter('shopware.filesystem.theme.use_last_modified_version_strategy')
            && !$container->getParameter('shopware.filesystem.theme.use_last_modified_version_strategy')
        ) {
            $container->removeDefinition('shopware.asset.theme.version_strategy');
            $container->setAlias('shopware.asset.theme.version_strategy', 'assets.empty_version_strategy');
        }
    }
}
