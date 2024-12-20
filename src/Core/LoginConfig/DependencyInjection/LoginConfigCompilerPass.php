<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\DependencyInjection;

use Shopware\Core\LoginConfig\ConfigBuilder\LoginConfigService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class LoginConfigCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(LoginConfigService::class)) {
            return;
        }

        $definition = $container->getDefinition(LoginConfigService::class);
        $taggedServices = $container->findTaggedServiceIds('shopware.login.config.handler');

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $definition->addMethodCall('addHandler', [
                    new Reference($id),
                    $attributes['key'],
                ]);
            }
        }
    }
}
