<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('sales-channel')]
class PartialSalesChannelEntityLoadedEvent extends SalesChannelEntityLoadedEvent
{
    /**
     * @var PartialEntity[]
     */
    protected $entities;

    public function __construct(
        EntityDefinition $definition,
        array $entities,
        SalesChannelContext $context
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
