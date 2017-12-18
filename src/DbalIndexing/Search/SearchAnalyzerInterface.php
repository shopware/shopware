<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Search;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Api\Product\Struct\ProductBasicStruct;

interface SearchAnalyzerInterface
{
    public function analyze(ProductBasicStruct $product, TranslationContext $context): array;
}
