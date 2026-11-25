<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\NoneSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
final class ContentLayoutAssignableCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(RootSourceRegistry::class)) {
            return;
        }

        $sources = $container->findTaggedServiceIds('content_system.entity_specification_source');

        $entityTypes = [];
        foreach ($sources as $serviceId => $tags) {
            $sourceDefinition = $container->getDefinition($serviceId);

            if ($sourceDefinition->getClass() === null) {
                continue;
            }

            $entityTypes[] = $this->extractEntityType($container, $serviceId, $sourceDefinition->getArguments());
        }

        $this->assertDisjointFromReservedRootSources($container, $entityTypes);

        $registry = $container->getDefinition(RootSourceRegistry::class);
        $registry->setArgument('$entityTypes', $entityTypes);
    }

    /**
     * RootSourceRegistry::sourceFor() probes the entity branch before the section locator and special-cases "none",
     * so an entity type sharing a section id or "none" would silently shadow it. The convention is documented on the
     * registry; enforce it here so a collision is a container-compile error, not a wrong-root-context resolution at
     * the first request.
     *
     * @param list<string> $entityTypes
     */
    private function assertDisjointFromReservedRootSources(ContainerBuilder $container, array $entityTypes): void
    {
        $reserved = [...$this->sectionIds($container), NoneSpecificationSource::ROOT_SOURCE];

        foreach ($entityTypes as $entityType) {
            if (\in_array($entityType, $reserved, true)) {
                throw DependencyInjectionException::rootSourceNamespaceCollision($entityType);
            }
        }
    }

    /**
     * The section ids the registry's section locator is keyed by — the "section" attribute of each
     * content_system.specification_source tagged service (header/footer; none in a headless deployment).
     *
     * @return list<string>
     */
    private function sectionIds(ContainerBuilder $container): array
    {
        $sections = [];
        foreach ($container->findTaggedServiceIds('content_system.specification_source') as $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['section']) && \is_string($tag['section'])) {
                    $sections[] = $tag['section'];
                }
            }
        }

        return $sections;
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private function extractEntityType(ContainerBuilder $container, string $serviceId, array $arguments): string
    {
        foreach ($arguments as $argument) {
            if (!$argument instanceof Reference) {
                continue;
            }

            $referencedId = (string) $argument;

            if (!$container->hasDefinition($referencedId)) {
                continue;
            }

            $referencedClass = $container->getDefinition($referencedId)->getClass();

            if ($referencedClass === null || !class_exists($referencedClass)) {
                continue;
            }

            if (!is_subclass_of($referencedClass, AbstractContentLayoutAssignableDefinition::class)) {
                continue;
            }

            $instance = new $referencedClass();

            return $instance->getContentLayoutEntityType();
        }

        throw DependencyInjectionException::missingAssignableDefinition($serviceId, 'content_system.entity_specification_source');
    }
}
