<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

interface AvailableStockCalculatorInterface
{
    public function calculate(string $productId, int $stock);
}