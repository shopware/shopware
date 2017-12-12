<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Search;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Struct\ProductDetailStruct;

class SearchAnalyzerRegistry
{
    /**
     * @var SearchAnalyzerInterface[]
     */
    protected $analyzers;

    public function __construct(iterable $analyzers)
    {
        $this->analyzers = $analyzers;
    }

    public function analyze(ProductDetailStruct $product, TranslationContext $context): array
    {
        $collection = [];

        foreach ($this->analyzers as $analyzer) {
            $collection = array_merge(
                $collection,
                $analyzer->analyze($product, $context)
            );
        }

        return array_unique($collection);
    }
}
