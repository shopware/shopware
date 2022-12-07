<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Query;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;

/**
 * @final
 */
class ScoreQuery extends Filter
{
    public function __construct(private Filter $query, private float $score, private ?string $scoreField = null)
    {
    }

    public function getFields(): array
    {
        return $this->query->getFields();
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getQuery(): Filter
    {
        return $this->query;
    }

    public function getScoreField(): ?string
    {
        return $this->scoreField;
    }
}
