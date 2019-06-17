<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;

class CreateIndexingCriteriaEvent extends NestedEvent
{
    public const NAME = 'es.create.indexing.criteria';

    /**
     * @var EntityDefinition
     */
    private $definition;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Criteria
     */
    private $criteria;

    public function __construct(EntityDefinition $definition, Criteria $criteria, Context $context)
    {
        $this->definition = $definition;
        $this->context = $context;
        $this->criteria = $criteria;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
