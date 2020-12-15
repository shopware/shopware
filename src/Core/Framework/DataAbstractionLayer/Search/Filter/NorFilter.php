<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

class NorFilter extends NotFilter
{
    public function __construct(array $queries = [])
    {
        parent::__construct(self::CONNECTION_OR, $queries);
    }
}
