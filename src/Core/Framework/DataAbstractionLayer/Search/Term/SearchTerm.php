<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

class SearchTerm
{
    /**
     * @var string
     */
    protected $term;

    /**
     * @var float
     */
    protected $score;

    public function __construct(string $term, float $score = 1.0)
    {
        $this->term = $term;
        $this->score = $score;
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
