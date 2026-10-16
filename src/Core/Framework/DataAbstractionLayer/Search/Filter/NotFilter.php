<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class NotFilter extends MultiFilter
{
    /**
     * @param string|Filter $operator the connection operator of `$queries`, or the single filter to negate
     * @param array<Filter> $queries
     */
    public function __construct(
        string|Filter $operator,
        array $queries = []
    ) {
        if (!$operator instanceof Filter) {
            parent::__construct($operator, $queries);

            return;
        }

        if ($queries !== []) {
            throw DataAbstractionLayerException::invalidFilterQuery('Additional queries cannot be passed when negating a single filter');
        }

        parent::__construct(self::CONNECTION_AND, [$operator]);
    }
}
