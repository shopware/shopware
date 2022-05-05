<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-final - Will be @final
 * @final
 */
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

    /**
     * @internal
     */
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
