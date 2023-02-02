<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final tag:v6.5.0
 */
class NandFilter extends NotFilter
{
    public function __construct(array $queries = [])
    {
        parent::__construct(self::CONNECTION_AND, $queries);
    }
}
