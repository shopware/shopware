<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Search;

use Shopware\Api\Search\Term\SearchFilterInterface;
use Shopware\Api\Search\Term\TokenizerInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Struct\ProductDetailStruct;

class LanguageAnalyzer implements SearchAnalyzerInterface
{
    /**
     * @var TokenizerInterface
     */
    private $tokenizer;

    /**
     * @var SearchFilterInterface
     */
    private $filter;

    public function __construct(TokenizerInterface $tokenizer, SearchFilterInterface $filter)
    {
        $this->tokenizer = $tokenizer;
        $this->filter = $filter;
    }

    public function analyze(ProductDetailStruct $product, TranslationContext $context): array
    {
        $tokens = array_merge(
            $this->tokenizer->tokenize($product->getName()),
            $this->tokenizer->tokenize((string) $product->getMetaTitle()),
            $this->tokenizer->tokenize((string) $product->getKeywords()),
            $this->tokenizer->tokenize($product->getManufacturer()->getName()),
            $this->tokenizer->tokenize((string) $product->getManufacturer()->getMetaTitle())
        );

        $longTokens = array_merge(
            $this->tokenizer->tokenize((string) $product->getDescription()),
            $this->tokenizer->tokenize((string) $product->getDescriptionLong())
        );

        $longTokens = $this->filter->filter($longTokens, $context);

        $tokens = array_merge($tokens, $longTokens);

        return $tokens;
    }
}
