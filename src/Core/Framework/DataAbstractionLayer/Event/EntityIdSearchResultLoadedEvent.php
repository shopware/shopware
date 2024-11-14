<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class EntityIdSearchResultLoadedEvent extends NestedEvent implements GenericEvent
{
    /**
     * @var IdSearchResult
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $result;

    /**
     * @var EntityDefinition
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $definition;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    public function __construct(
        EntityDefinition $definition,
        IdSearchResult $result
    ) {
        $this->result = $result;
        $this->definition = $definition;
        $this->name = $this->definition->getEntityName() . '.id.search.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
