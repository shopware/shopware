<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class EntityLoadedEvent extends NestedEvent implements GenericEvent
{
    /**
     * @var Entity[]
     */
    protected $entities;

    /**
     * @var EntityDefinition
     */
    protected $definition;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $name;

    /**
     * @param Entity[] $entities
     */
    public function __construct(
        EntityDefinition $definition,
        array $entities,
        Context $context
    ) {
        $this->entities = $entities;
        $this->definition = $definition;
        $this->context = $context;
        $this->name = $this->definition->getEntityName() . '.loaded';
    }

    /**
     * @return Entity[]
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

    public function getEvents(): ?NestedEventCollection
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        $ids = [];

        foreach ($this->entities as $entity) {
            $ids[] = $entity->getUniqueIdentifier();
        }

        return $ids;
    }
}
