<?php declare(strict_types=1);

namespace Shopware\Api\Search\Query;

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

    public function __construct(Query $query, float $score)
    {
        $this->score = $score;
        $this->query = $query;
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
}
