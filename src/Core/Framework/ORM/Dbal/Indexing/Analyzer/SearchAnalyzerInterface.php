<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing\Analyzer;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Product\Struct\ProductBasicStruct;

interface SearchAnalyzerInterface
{
    public function analyze(ProductBasicStruct $product, ApplicationContext $context): array;
}
