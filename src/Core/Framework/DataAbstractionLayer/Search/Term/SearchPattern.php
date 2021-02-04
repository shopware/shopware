<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

class SearchPattern
{
    public const BOOLEAN_CLAUSE_AND = 'boolean_clause_and';
    public const BOOLEAN_CLAUSE_OR = 'boolean_clause_or';

    /**
     * @var SearchTerm
     */
    protected $original;

    /**
     * @var SearchTerm[]
     */
    protected $terms = [];

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     *
     * @var array
     */
    protected $tokenTerms = [];

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     *
     * @var string
     */
    protected $booleanClause;

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

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     */
    public function setBooleanClause(bool $booleanClauseAnd): void
    {
        $this->booleanClause = $this->getBooleanClauseMapping($booleanClauseAnd);
    }

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     */
    public function getBooleanClause(): string
    {
        return $this->booleanClause ?? self::BOOLEAN_CLAUSE_OR;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     */
    public function setTokenTerms(array $tokenTerms): void
    {
        $this->tokenTerms = $tokenTerms;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     */
    public function getTokenTerms(): array
    {
        return $this->tokenTerms;
    }

    public function getAllTerms(): array
    {
        $terms = [$this->original->getTerm()];
        foreach ($this->terms as $term) {
            $terms[] = $term->getTerm();
        }

        return $terms;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     */
    private function getBooleanClauseMapping(bool $booleanClauseAnd): string
    {
        return $booleanClauseAnd ? self::BOOLEAN_CLAUSE_AND : self::BOOLEAN_CLAUSE_OR;
    }
}
