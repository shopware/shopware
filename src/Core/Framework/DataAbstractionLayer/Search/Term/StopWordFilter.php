<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Context;
use voku\helper\StopWords;

class StopWordFilter implements SearchFilterInterface
{
    /**
     * @var StopWords
     */
    private $filter;

    /**
     * @var array
     */
    private $words;

    public function __construct()
    {
        $this->filter = new StopWords();
    }

    public function filter(array $tokens, Context $context): array
    {
        $words = $this->loadWords();

        $tokens = array_diff_key($tokens, $words);

        $tokens = $this->filterLength($tokens);

        return $tokens;
    }

    private function loadWords(): array
    {
        if ($this->words) {
            return $this->words;
        }
        $words = $this->filter->getStopWordsFromLanguage('en');
        $words = array_flip($words);

        $fallback = $this->filter->getStopWordsFromLanguage();
        $fallback = array_flip($fallback);

        return $this->words = array_merge($fallback, $words);
    }

    private function filterLength(array $tokens): array
    {
        $filtered = [];

        foreach ($tokens as $word => $ranking) {
            $word = (string) $word;
            if (\mb_strlen($word) >= 3) {
                $filtered[$word] = $ranking;
            }
        }

        return $filtered;
    }
}
