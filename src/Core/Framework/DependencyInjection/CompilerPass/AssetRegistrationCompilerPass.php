<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Storefront\Theme\ThemeCompiler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AssetRegistrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $assets = [];
        foreach ($container->findTaggedServiceIds('shopware.asset') as $id => $config) {
            $container->getDefinition($id)->addTag('assets.package', ['package' => $config[0]['asset']]);
            $assets[$config[0]['asset']] = new Reference($id);
        }

        $assetService = $container->getDefinition('assets.packages');
        $assetService->addMethodCall('setDefaultPackage', [$assets['asset']]);

        if ($container->hasDefinition(ThemeCompiler::class)) {
            $container->getDefinition(ThemeCompiler::class)->replaceArgument(7, $assets);
        }
    }
}
