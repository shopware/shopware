<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails the container build when a tagged data loader cannot satisfy the type-introspection contract:
 * a resolvable class must extend AbstractContentDataLoader and carry a resolvable `@extends` annotation.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemDataLoaderTypeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $loaders = $container->findTaggedServiceIds('content_system.data_loader');

        foreach ($loaders as $serviceId => $tags) {
            $class = $container->getDefinition($serviceId)->getClass();

            if ($class === null || !class_exists($class)) {
                // No resolvable class to introspect; leave it for Symfony's own service validation.
                continue;
            }

            if (!is_subclass_of($class, AbstractContentDataLoader::class)) {
                throw DependencyInjectionException::taggedServiceHasWrongType($serviceId, 'content_system.data_loader', AbstractContentDataLoader::class);
            }

            /** @var class-string<AbstractContentDataLoader<Struct>> $class */
            $class::extendsDescriptor();
        }
    }
}
