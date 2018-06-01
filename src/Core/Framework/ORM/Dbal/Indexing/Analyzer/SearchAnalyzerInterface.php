<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing\Analyzer;

use Shopware\Framework\Context;
use Shopware\Content\Product\Struct\ProductBasicStruct;

interface SearchAnalyzerInterface
{
    public function analyze(ProductBasicStruct $product, Context $context): array;
}
