<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer\Analyzer;

use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Context\Struct\ApplicationContext;

interface SearchAnalyzerInterface
{
    public function analyze(ProductBasicStruct $product, ApplicationContext $context): array;
}
