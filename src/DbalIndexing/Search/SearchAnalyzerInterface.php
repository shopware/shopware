<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Search;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Struct\ProductDetailStruct;

interface SearchAnalyzerInterface
{
    public function analyze(ProductDetailStruct $product, TranslationContext $context): array;
}
