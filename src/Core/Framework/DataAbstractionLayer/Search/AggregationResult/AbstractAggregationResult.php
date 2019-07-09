<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

use Shopware\Core\Framework\Struct\Struct;

abstract class AbstractAggregationResult extends Struct
{
    /**
     * @var array|null
     */
    protected $key;

    public function __construct(?array $key)
    {
        $this->key = $key;
    }

    public function getKey(): ?array
    {
        return $this->key;
    }
}
