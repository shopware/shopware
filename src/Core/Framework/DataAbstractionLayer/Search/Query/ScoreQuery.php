<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Query;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;

class ScoreQuery extends Filter
{
    /**
     * @var float
     */
    protected $score;

    /**
     * @var Filter
     */
    protected $query;

    /**
     * @var string|null
     */
    protected $scoreField;

    public function __construct(Filter $query, float $score, ?string $scoreField = null)
    {
        $this->score = $score;
        $this->query = $query;
        $this->scoreField = $scoreField;
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
