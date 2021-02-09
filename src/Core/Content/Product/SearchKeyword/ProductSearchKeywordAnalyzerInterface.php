<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

interface ProductSearchKeywordAnalyzerInterface
{
    /**@feature-deprecated (flag:FEATURE_NEXT_10552) tag:v6.4.0 - Parameter $configFields will be mandatory in future implementation */
    public function analyze(ProductEntity $product, Context $context/*, ?array $configFields */): AnalyzedKeywordCollection;
}
