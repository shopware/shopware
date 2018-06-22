<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ORM\Indexing;

use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Framework\Context;

interface ProductSearchAnalyzerInterface
{
    public function analyze(ProductStruct $product, Context $context): array;
}
