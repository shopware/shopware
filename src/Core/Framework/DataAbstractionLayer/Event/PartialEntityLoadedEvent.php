<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class PartialEntityLoadedEvent extends EntityLoadedEvent
{
    /**
     * @var PartialEntity[]
     */
    protected $entities;

    /**
     * @param PartialEntity[] $entities
     */
    public function __construct(
        EntityDefinition $definition,
        array $entities,
        Context $context
    ) {
        parent::__construct($definition, $entities, $context);
        $this->name = $this->definition->getEntityName() . '.partial_loaded';
    }

    /**
     * @return PartialEntity[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
