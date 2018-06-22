<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;

class EntitySearchResultLoadedEvent extends NestedEvent
{
    /**
     * @var EntitySearchResult
     */
    protected $result;

    /**
     * @var string|EntityDefinition
     */
    protected $definition;

    /**
     * @var string
     */
    protected $name;

    public function __construct(string $definition, EntitySearchResult $result)
    {
        $this->result = $result;
        $this->definition = $definition;
        $this->name = $this->definition::getEntityName() . '.search.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
