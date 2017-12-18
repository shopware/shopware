<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Term;

use Shopware\Context\Struct\TranslationContext;
use voku\helper\StopWords;

class StopWordFilter implements SearchFilterInterface
{
    public function filter(array $tokens, TranslationContext $context): array
    {
        $stopWords = new StopWords();

        $words = $stopWords->getStopWordsFromLanguage('en');
        $fallback = $stopWords->getStopWordsFromLanguage();
        $words = array_merge($fallback, $words);

        $tokens = array_diff($tokens, $words);
        $tokens = array_values($tokens);

        $tokens = array_filter($tokens, function ($token) {
            return strlen($token) > 3;
        });

        return $tokens;
    }
}
