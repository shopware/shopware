<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Term;

use Shopware\Context\Struct\ApplicationContext;
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

    public function filter(array $tokens, ApplicationContext $context): array
    {
        $words = $this->loadWords();

        $tokens = $this->filterWords($tokens, $words);

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

    private function filterWords(array $tokens, array $words): array
    {
        $tokens = array_flip($tokens);
        $tokens = array_diff_key($tokens, $words);

        return array_keys($tokens);
    }

    private function filterLength(array $tokens): array
    {
        return array_filter($tokens, function ($token) {
            return strlen($token) > 3;
        });
    }
}
