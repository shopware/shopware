<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
class EntityLoadedEventFactory
{
    private DefinitionInstanceRegistry $registry;

    public function __construct(DefinitionInstanceRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function create(array $entities, Context $context): EntityLoadedContainerEvent
    {
        $mapping = $this->recursion($entities, []);

        $generator = function (EntityDefinition $definition, array $entities) use ($context) {
            return new EntityLoadedEvent($definition, $entities, $context, false);
        };

        return $this->buildEvents($mapping, $generator, $context);
    }

    /**
     * @return EntityLoadedContainerEvent[]
     */
    public function createForSalesChannel(array $entities, SalesChannelContext $context): array
    {
        $mapping = $this->recursion($entities, []);

        $generator = function (EntityDefinition $definition, array $entities) use ($context) {
            return new EntityLoadedEvent($definition, $entities, $context->getContext(), false);
        };

        $salesGenerator = function (EntityDefinition $definition, array $entities) use ($context) {
            return new SalesChannelEntityLoadedEvent($definition, $entities, $context, false);
        };

        return [
            $this->buildEvents($mapping, $generator, $context->getContext()),
            $this->buildEvents($mapping, $salesGenerator, $context->getContext()),
        ];
    }

    private function buildEvents(array $mapping, \Closure $generator, Context $context): EntityLoadedContainerEvent
    {
        $events = [];
        foreach ($mapping as $name => $entities) {
            $definition = $this->registry->getByEntityName($name);

            $events[] = $generator($definition, $entities);
        }

        return new EntityLoadedContainerEvent($context, $events);
    }

    private function recursion(array $entities, array $mapping): array
    {
        foreach ($entities as $entity) {
            if (!$entity instanceof Entity) {
                continue;
            }

            $mapping = $this->map($entity, $mapping);
        }

        return $mapping;
    }

    private function map(Entity $entity, array $mapping): array
    {
        $mapping[$entity->getInternalEntityName()][] = $entity;

        $vars = $entity->getVars();
        foreach ($vars as $value) {
            if ($value instanceof Entity) {
                $mapping = $this->map($value, $mapping);

                continue;
            }

            if ($value instanceof Collection) {
                $value = $value->getElements();
            }
            if (!\is_array($value)) {
                continue;
            }

            $mapping = $this->recursion($value, $mapping);
        }

        return $mapping;
    }
}
