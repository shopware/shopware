<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\AttributeBasedEntityDefinition;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Checks that all AttributeBasedEntityDefinition implementations have the required
 * 'entity' attribute on their 'shopware.entity.definition' tag.
 *
 * @internal
 */
#[Package('framework')]
class AttributeEntityTagCheckCompilerPass implements CompilerPassInterface
{
    private const TAG_NAME = 'shopware.entity.definition';

    public function process(ContainerBuilder $container): void
    {
        $serviceIds = array_keys($container->findTaggedServiceIds(self::TAG_NAME));

        foreach ($serviceIds as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();

            if ($class === null || !is_a($class, AttributeBasedEntityDefinition::class, true)) {
                continue;
            }

            if ($this->hasEntityTagAttribute($definition)) {
                continue;
            }

            throw DependencyInjectionException::attributeEntityDefinitionMissingEntityTag($serviceId, $class);
        }
    }

    private function hasEntityTagAttribute(Definition $definition): bool
    {
        foreach ($definition->getTag(self::TAG_NAME) as $attributes) {
            if (isset($attributes['entity']) && $attributes['entity'] !== '') {
                return true;
            }
        }

        return false;
    }
}
