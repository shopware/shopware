<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Term;

use Shopware\Application\Context\Struct\ApplicationContext;

class SearchTermInterpreter
{
    /**
     * @var TokenizerInterface
     */
    private $tokenizer;

    public function __construct(TokenizerInterface $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    public function interpret(string $term, ApplicationContext $context): SearchPattern
    {
        $terms = $this->tokenizer->tokenize($term);

        $pattern = new SearchPattern(
            new SearchTerm($term)
        );

        if (count($terms) === 1) {
            return $pattern;
        }

        foreach ($terms as $part) {
            $percent = strlen($part) / strlen($term);
            $pattern->addTerm(new SearchTerm($part, $percent));
        }

        return $pattern;
    }
}
