<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends SalesChannelEntityLoadedEvent<PartialEntity>
 */
#[Package('discovery')]
class PartialSalesChannelEntityLoadedEvent extends SalesChannelEntityLoadedEvent
{
    /**
     * @param PartialEntity[] $entities
     */
    public function __construct(
        EntityDefinition $definition,
        array $entities,
        SalesChannelContext $context
    ) {
        parent::__construct($definition, $entities, $context);

        $this->name = $this->definition->getEntityName() . '.partial_loaded';
    }
}
