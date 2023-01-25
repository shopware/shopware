<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class SearchPattern
{
    final public const BOOLEAN_CLAUSE_AND = 'boolean_clause_and';
    final public const BOOLEAN_CLAUSE_OR = 'boolean_clause_or';

    /**
     * @var SearchTerm[]
     */
    private array $terms = [];

    /**
     * @var array<int, list<string>>
     */
    private array $tokenTerms = [];

    private ?string $booleanClause = null;

    public function __construct(private readonly SearchTerm $original)
    {
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

    public function setBooleanClause(bool $booleanClauseAnd): void
    {
        $this->booleanClause = $this->getBooleanClauseMapping($booleanClauseAnd);
    }

    public function getBooleanClause(): string
    {
        return $this->booleanClause ?? self::BOOLEAN_CLAUSE_OR;
    }

    /**
     * @param array<int, list<string>> $tokenTerms
     */
    public function setTokenTerms(array $tokenTerms): void
    {
        $this->tokenTerms = $tokenTerms;
    }

    /**
     * @return array<int, list<string>>
     */
    public function getTokenTerms(): array
    {
        return $this->tokenTerms;
    }

    /**
     * @return list<string>
     */
    public function getAllTerms(): array
    {
        $terms = [$this->original->getTerm()];
        foreach ($this->terms as $term) {
            $terms[] = $term->getTerm();
        }

        return $terms;
    }

    private function getBooleanClauseMapping(bool $booleanClauseAnd): string
    {
        return $booleanClauseAnd ? self::BOOLEAN_CLAUSE_AND : self::BOOLEAN_CLAUSE_OR;
    }
}
