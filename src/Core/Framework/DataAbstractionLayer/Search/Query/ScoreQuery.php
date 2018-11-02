<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Query;

class ScoreQuery extends Query
{
    /**
     * @var float
     */
    protected $score;

    /**
     * @var Query
     */
    protected $query;

    /**
     * @var null|string
     */
    protected $scoreField;

    public function __construct(Query $query, float $score, ?string $scoreField = null)
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

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function getScoreField(): ?string
    {
        return $this->scoreField;
    }
}
