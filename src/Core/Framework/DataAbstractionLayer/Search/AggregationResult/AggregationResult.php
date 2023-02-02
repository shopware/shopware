<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
abstract class AggregationResult extends Struct
{
    /**
     * @var string
     */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getApiAlias(): string
    {
        return $this->name . '_aggregation';
    }
}
