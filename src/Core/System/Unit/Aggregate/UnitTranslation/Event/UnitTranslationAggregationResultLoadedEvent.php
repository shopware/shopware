<?php declare(strict_types=1);

namespace Shopware\System\Unit\Aggregate\UnitTranslation\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\ORM\Search\AggregatorResult;

class UnitTranslationAggregationResultLoadedEvent extends NestedEvent
{
    public const NAME = 'unit_translation.aggregation.result.loaded';

    /**
     * @var AggregatorResult
     */
    protected $result;

    public function __construct(AggregatorResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }

    public function getResult(): AggregatorResult
    {
        return $this->result;
    }
}
