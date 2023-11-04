<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class EntitySearchResultLoadedEvent extends NestedEvent implements GenericEvent
{
    /**
     * @var EntitySearchResult
     */
    protected $result;

    /**
     * @var EntityDefinition
     */
    protected $definition;

    /**
     * @var string
     */
    protected $name;

    public function __construct(
        EntityDefinition $definition,
        EntitySearchResult $result
    ) {
        $this->result = $result;
        $this->definition = $definition;
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

    public function getResult(): EntitySearchResult
    {
        return $this->result;
    }
}
