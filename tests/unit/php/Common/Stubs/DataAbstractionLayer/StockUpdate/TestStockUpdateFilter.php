<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StockUpdate;

use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
class TestStockUpdateFilter extends AbstractStockUpdateFilter
{
    /**
     * @var list<string>
     */
    private array $ids;

    /**
     * @param list<string> $ids
     */
    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public function filter(array $ids, Context $context): array
    {
        return \array_values(\array_diff($ids, $this->ids));
    }
}
