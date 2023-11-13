<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class XOrFilter extends MultiFilter
{
    /**
     * @param  Filter[] $queries
     */
    public function __construct(array $queries = [])
    {
        parent::__construct(self::CONNECTION_XOR, $queries);
    }
}
