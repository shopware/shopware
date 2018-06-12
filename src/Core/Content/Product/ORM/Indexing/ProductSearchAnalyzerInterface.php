<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ORM\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\ProductBasicStruct;

interface ProductSearchAnalyzerInterface
{
    public function analyze(ProductBasicStruct $product, Context $context): array;
}
