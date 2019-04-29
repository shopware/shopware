<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

interface ProductSearchKeywordAnalyzerInterface
{
    public function analyze(ProductEntity $product, Context $context): AnalyzedKeywordCollection;
}
