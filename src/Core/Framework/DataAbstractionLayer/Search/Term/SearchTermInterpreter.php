<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class SearchTermInterpreter
{
    /**
     * @internal
     */
    public function __construct(private readonly TokenizerInterface $tokenizer)
    {
    }

    public function interpret(string $term): SearchPattern
    {
        $terms = $this->tokenizer->tokenize($term);

        $pattern = new SearchPattern(new SearchTerm($term));

        if (\count($terms) === 1) {
            return $pattern;
        }

        foreach ($terms as $part) {
            $percent = mb_strlen($part) / mb_strlen($term);
            $pattern->addTerm(new SearchTerm($part, $percent));
        }

        return $pattern;
    }
}
