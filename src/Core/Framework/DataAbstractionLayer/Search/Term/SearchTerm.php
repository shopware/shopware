<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

/**
 * @final
 */
class SearchTerm
{
    /**
     * @internal
     */
    public function __construct(private string $term, private float $score = 1.0)
    {
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getScore(): float
    {
        return $this->score;
    }
}
