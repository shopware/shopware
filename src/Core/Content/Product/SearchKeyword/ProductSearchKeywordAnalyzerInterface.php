<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

interface ProductSearchKeywordAnalyzerInterface
{
    /* @deprecated tag:v6.4.0 - using analyzeBaseOnSearchConfig instead  */
    public function analyze(ProductEntity $product, Context $context): AnalyzedKeywordCollection;

    public function analyzeBaseOnSearchConfig(ProductEntity $product, Context $context, ProductSearchConfigFieldCollection $configFields): AnalyzedKeywordCollection;
}
