<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;

interface ProductSearchKeywordAnalyzerInterface
{
    /**
     * @param array<int, array{field: string, tokenize: bool, ranking: int}> $configFields
     */
    public function analyze(ProductEntity $product, Context $context, array $configFields): AnalyzedKeywordCollection;
}
