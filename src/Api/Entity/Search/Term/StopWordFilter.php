<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Term;

use Shopware\Context\Struct\ShopContext;
use voku\helper\StopWords;

class StopWordFilter implements SearchFilterInterface
{
    /**
     * @var StopWords
     */
    private $filter;

    public function __construct()
    {
        $this->filter = new StopWords();
    }

    public function filter(array $tokens, ShopContext $context): array
    {
        $words = $this->filter->getStopWordsFromLanguage('en');
        $fallback = $this->filter->getStopWordsFromLanguage();
        $words = array_merge($fallback, $words);

        $tokens = array_diff($tokens, $words);
        $tokens = array_values($tokens);

        $tokens = array_filter($tokens, function ($token) {
            return strlen($token) > 3;
        });

        return $tokens;
    }
}
