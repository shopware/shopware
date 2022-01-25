<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;

/**
 * @internal
 */
class PartialEntityLoadedEvent extends NestedEvent implements GenericEvent
{
    /**
     * @var PartialEntity[]
     */
    protected array $entities;

    protected EntityDefinition$definition;

    protected Context $context;

    protected string $name;

    public function __construct(EntityDefinition $definition, array $entities, Context $context)
    {
        $this->entities = $entities;
        $this->definition = $definition;
        $this->context = $context;
        $this->name = $this->definition->getEntityName() . '.partial_loaded';
    }

    /**
     * @return PartialEntity[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIds(): array
    {
        $ids = [];

        foreach ($this->getEntities() as $entity) {
            $ids[] = $entity->getUniqueIdentifier();
        }

        return $ids;
    }
}
