<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Write\Flag;

/**
 * Defines the weight for a search query on the entity for this field
 */
class SearchRanking extends Flag
{
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
}
