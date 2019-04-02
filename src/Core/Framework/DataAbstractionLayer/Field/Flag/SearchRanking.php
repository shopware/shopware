<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Defines the weight for a search query on the entity for this field
 */
class SearchRanking extends Flag
{
    public const ASSOCIATION_SEARCH_RANKING = 0.25;
    public const MIDDLE_SEARCH_RANKING = 250;
    public const LOW_SEARCH_RAKING = 80;
    public const HIGH_SEARCH_RANKING = 500;

    /**
     * @var float
     */
    protected $ranking;

    public function __construct(float $ranking)
    {
        $this->ranking = $ranking;
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function parse(): \Generator
    {
        yield 'search_ranking' => $this->ranking;
    }
}
