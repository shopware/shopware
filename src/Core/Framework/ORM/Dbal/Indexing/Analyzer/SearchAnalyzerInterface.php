<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\Indexing\Analyzer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Struct\ProductBasicStruct;

interface SearchAnalyzerInterface
{
    public function analyze(ProductBasicStruct $product, Context $context): array;
}
