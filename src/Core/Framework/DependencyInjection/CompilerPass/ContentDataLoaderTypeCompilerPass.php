<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Schema\AvailableDataResolver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
class ContentDataLoaderTypeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AvailableDataResolver::class)) {
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

            /** @var class-string<AbstractContentDataLoader<\Shopware\Core\Framework\Struct\Struct>> $class */
            $source = $class::getRequirementType();
            $descriptor = $class::getProvidedData();

            $sourceToTypes[$source][] = [
                'className' => $descriptor->className,
                'genericParameters' => $descriptor->genericParameters,
            ];
        }

        $resolver = $container->getDefinition(AvailableDataResolver::class);
        $resolver->setArgument('$compiledSourceToTypes', $sourceToTypes);
    }
}
