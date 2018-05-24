<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing\Analyzer;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Product\Struct\ProductBasicStruct;
use Shopware\Framework\ORM\Search\Term\SearchFilterInterface;
use Shopware\Framework\ORM\Search\Term\TokenizerInterface;

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

    public function analyze(ProductBasicStruct $product, ApplicationContext $context): array
    {
        $tokens = [];
        $tokens = $this->mergeTokens($tokens, $this->tokenizer->tokenize((string) $product->getName()), 500);
        $tokens = $this->mergeTokens($tokens, $this->tokenizer->tokenize((string) $product->getKeywords()), 400);
        $tokens = $this->mergeTokens($tokens, $this->tokenizer->tokenize((string) $product->getMetaTitle()), 200);

        if ($product->getManufacturer()) {
            $tokens = $this->mergeTokens($tokens, $this->tokenizer->tokenize($product->getManufacturer()->getName()), 100);
            $tokens = $this->mergeTokens($tokens, $this->tokenizer->tokenize((string) $product->getManufacturer()->getMetaTitle()), 50);
        }

        $longTokens = array_merge(
            $this->tokenizer->tokenize((string) $product->getDescription()),
            $this->tokenizer->tokenize((string) $product->getDescriptionLong())
        );
        $longTokens = $this->filter->filter($longTokens, $context);

        $tokens = $this->mergeTokens($tokens, $longTokens, 5);

        return $tokens;
    }

    private function mergeTokens(array $existing, array $new, float $ranking)
    {
        foreach ($new as $keyword) {
            $before = 0;

            if (array_key_exists($keyword, $existing)) {
                $before = $existing[$keyword];
            }

            $existing[$keyword] = max($before, $ranking);
        }

        return $existing;
    }
}
