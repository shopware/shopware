<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Ensures all entity definition services have the 'entity' attribute on their tag.
 *
 * Attribute-based definitions already have it (set by AttributeEntityCompilerPass).
 * Class-based definitions get the entity name resolved via instantiation and written to the tag.
 *
 * After this pass, downstream passes can read entity names from tags without instantiation.
 *
 * @internal
 */
#[Package('framework')]
class EntityDefinitionTagCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $taggedServices = [
            'shopware.entity.definition' => $container->findTaggedServiceIds('shopware.entity.definition'),
            'shopware.sales_channel.entity.definition' => $container->findTaggedServiceIds('shopware.sales_channel.entity.definition'),
        ];

        foreach ($taggedServices as $tagName => $services) {
            foreach ($services as $serviceId => $tags) {
                $alreadyDefinedTagAttribute = $tags[0]['entity'] ?? null;
                $alreadyHasTagAttribute = $alreadyDefinedTagAttribute !== null && $alreadyDefinedTagAttribute !== '';

                $serviceDefinition = $container->getDefinition($serviceId);
                $class = $serviceDefinition->getClass();

                if ($class === null || !is_subclass_of($class, EntityDefinition::class)) {
                    continue;
                }

                $isEntityDefinitionInstantiatable = $this->isEntityDefinitionInstantiatableDuringCompilation($class);

                $entityName = $isEntityDefinitionInstantiatable ? (new $class())->getEntityName() : null;

                if ($alreadyHasTagAttribute) {
                    if ($entityName !== null && $alreadyDefinedTagAttribute !== $entityName) {
                        /** @deprecated tag:v6.8.0 - remove else branch, keep only the throw */
                        if (Feature::isActive('v6.8.0.0')) {
                            throw DependencyInjectionException::entityTagMismatch($serviceId, $tagName, $alreadyDefinedTagAttribute, $entityName);
                        }

                        Feature::triggerDeprecationOrThrow(
                            'v6.8.0.0',
                            'Service "' . $serviceId . '" has tag "' . $tagName . '" with entity="' . $alreadyDefinedTagAttribute . '", but getEntityName() returns "' . $entityName . '". They must match. This will throw an exception in v6.8.0.'
                        );
                    }

                    continue;
                }

                if ($entityName === null) {
                    /** @deprecated tag:v6.8.0 - remove else branch, keep only the throw */
                    if (Feature::isActive('v6.8.0.0')) {
                        throw DependencyInjectionException::entityTagUnresolvable($serviceId, $tagName, $class);
                    }

                    Feature::triggerDeprecationOrThrow(
                        'v6.8.0.0',
                        'Service "' . $serviceId . '" is tagged as "' . $tagName . '" but has no "entity" attribute and the entity name could not be resolved from class "' . $class . '". Add the "entity" attribute to the service tag. This will throw an exception in v6.8.0.'
                    );

                    continue;
                }

                // Write entity name to tag while preserving existing attributes
                $existingTags = $serviceDefinition->getTag($tagName);
                $serviceDefinition->clearTag($tagName);

                foreach ($existingTags as $attributes) {
                    if (!isset($attributes['entity']) || $attributes['entity'] === '') {
                        $attributes['entity'] = $entityName;
                    }

                    $serviceDefinition->addTag($tagName, $attributes);
                }
            }
        }
    }

    /**
     * @param class-string $class
     */
    private function isEntityDefinitionInstantiatableDuringCompilation(string $class): bool
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            return true;
        }

        if ($constructor->isPublic() === false) {
            return false;
        }

        if ($constructor->getNumberOfParameters() === 0) {
            return true;
        }

        return false;
    }
}
