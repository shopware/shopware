<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigForm;

use Shopware\Framework\ORM\Search\AggregatorResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormAggregationResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form.aggregation.result.loaded';

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
