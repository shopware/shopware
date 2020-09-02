<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;

class ProductSearchKeywordAnalyzer implements ProductSearchKeywordAnalyzerInterface
{
    /**
     * @var TokenizerInterface
     */
    private $tokenizer;

    public function __construct(TokenizerInterface $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    public function analyze(ProductEntity $product, Context $context): AnalyzedKeywordCollection
    {
        $keywords = new AnalyzedKeywordCollection();

        $keywords->add(new AnalyzedKeyword($product->getProductNumber(), 1000));

        $name = $product->getTranslation('name');
        if ($name) {
            $tokens = $this->tokenizer->tokenize((string) $name);
            foreach ($tokens as $token) {
                $keywords->add(new AnalyzedKeyword((string) $token, 700));
            }
        }

        if ($product->getManufacturer() && $product->getManufacturer()->getTranslation('name') !== null) {
            $keywords->add(new AnalyzedKeyword((string) $product->getManufacturer()->getTranslation('name'), 500));
        }
        if ($product->getManufacturerNumber()) {
            $keywords->add(new AnalyzedKeyword($product->getManufacturerNumber(), 500));
        }
        if ($product->getEan()) {
            $keywords->add(new AnalyzedKeyword($product->getEan(), 500));
        }

        return $keywords;
    }
}
