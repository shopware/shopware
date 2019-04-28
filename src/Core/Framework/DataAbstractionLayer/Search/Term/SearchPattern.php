<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

class SearchPattern
{
    /**
     * @var SearchTerm
     */
    protected $original;

    /**
     * @var SearchTerm[]
     */
    protected $terms = [];

    public function __construct(SearchTerm $original)
    {
        $this->original = $original;
    }

    public function getOriginal(): SearchTerm
    {
        return $this->original;
    }

    /**
     * @return SearchTerm[]
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    public function addTerm(SearchTerm $term): void
    {
        $this->terms[] = $term;
    }

    public function getAllTerms(): array
    {
        $terms = [$this->original->getTerm()];
        foreach ($this->terms as $term) {
            $terms[] = $term->getTerm();
        }

        return $terms;
    }
}
