<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ORM\Indexing;

use Shopware\Core\Content\Product\ORM\Indexing\ProductSearchAnalyzerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Struct\ProductBasicStruct;

class ProductSearchAnalyzerRegistry
{
    /**
     * @var ProductSearchAnalyzerInterface[]
     */
    protected $analyzers;

    public function __construct(iterable $analyzers)
    {
        $this->analyzers = $analyzers;
    }

    public function analyze(ProductBasicStruct $product, Context $context): array
    {
        $collection = [];

        foreach ($this->analyzers as $analyzer) {
            $keywords = $analyzer->analyze($product, $context);

            foreach ($keywords as $keyword => $ranking) {
                $before = 0;

                if (array_key_exists($keyword, $collection)) {
                    $before = $collection[$keyword];
                }

                $collection[$keyword] = max($before, $ranking);
            }
        }

        return $collection;
    }
}
