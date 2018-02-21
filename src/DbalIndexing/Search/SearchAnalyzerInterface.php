<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Search;

use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Context\Struct\ShopContext;

interface SearchAnalyzerInterface
{
    public function analyze(ProductBasicStruct $product, ShopContext $context): array;
}
