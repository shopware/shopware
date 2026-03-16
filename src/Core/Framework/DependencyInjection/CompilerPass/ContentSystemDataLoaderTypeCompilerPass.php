<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
final class ContentSystemDataLoaderTypeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ContentSystemDataLoaderTypeResolver::class)) {
            return;
        }

        $loaders = $container->findTaggedServiceIds('content_system.data_loader');

        $sourceToTypes = [];
        foreach ($loaders as $serviceId => $tags) {
            $class = $container->getDefinition($serviceId)->getClass();

            if ($class === null || !class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, AbstractContentDataLoader::class)) {
                continue;
            }

            /** @var class-string<AbstractContentDataLoader<Struct>> $class */
            $source = $class::getRequirementType();
            $descriptor = $class::getProvidedData();

            $sourceToTypes[$source][] = [
                'className' => $descriptor->className,
                'genericParameters' => $descriptor->genericParameters,
            ];
        }

        $resolver = $container->getDefinition(ContentSystemDataLoaderTypeResolver::class);
        $resolver->setArgument('$compiledSourceToTypes', $sourceToTypes);
    }
}
