<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

class GreaterThanEqualFilter extends RangeFilter
{
    public function __construct(string $field, $value)
    {
        parent::__construct($field, [
            RangeFilter::GTE => $value,
        ]);
    }
}
