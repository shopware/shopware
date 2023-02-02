<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class AndFilter extends MultiFilter
{
    public function __construct(array $queries = [])
    {
        parent::__construct(self::CONNECTION_AND, $queries);
    }
}
