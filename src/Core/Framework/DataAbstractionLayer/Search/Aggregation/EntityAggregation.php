<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

class EntityAggregation implements Aggregation
{
    use AggregationTrait;

    /**
     * @var string
     */
    private $definition;

    public function __construct(string $field, string $definition, string $name)
    {
        $this->field = $field;
        $this->name = $name;
        $this->definition = $definition;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }
}
