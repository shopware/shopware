<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\Struct\Struct;

class EntityAggregation extends Struct implements Aggregation
{
    use AggregationTrait;

    /**
     * @var string
     */
    private $definition;

    public function __construct(string $field, string $definition, string $name, string ...$groupByFields)
    {
        $this->field = $field;
        $this->name = $name;
        $this->definition = $definition;
        $this->groupByFields = $groupByFields;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }
}
