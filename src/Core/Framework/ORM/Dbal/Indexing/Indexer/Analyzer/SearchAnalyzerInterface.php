<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing\Indexer\Analyzer;

use Shopware\Content\Product\Struct\ProductBasicStruct;
use Shopware\Application\Context\Struct\ApplicationContext;

interface SearchAnalyzerInterface
{
    public function analyze(ProductBasicStruct $product, ApplicationContext $context): array;
}
