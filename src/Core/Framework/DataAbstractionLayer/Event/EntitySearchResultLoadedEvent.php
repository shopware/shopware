<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TEntityCollection of EntityCollection
 */
#[Package('framework')]
class EntitySearchResultLoadedEvent extends NestedEvent implements GenericEvent
{
    protected string $name;

    /**
     * @param EntitySearchResult<TEntityCollection> $result
     */
    public function __construct(
        protected EntityDefinition $definition,
        protected EntitySearchResult $result
    ) {
        $this->name = $this->definition->getEntityName() . '.search.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }

    /**
     * @return EntitySearchResult<TEntityCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->result;
    }
}
