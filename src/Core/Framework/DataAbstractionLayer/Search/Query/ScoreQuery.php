<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Query;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class ScoreQuery extends Filter
{
    public function __construct(
        private readonly Filter $query,
        private readonly float $score,
        private readonly ?string $scoreField = null
    ) {
    }

    public function jsonSerialize(): array
    {
        $value = parent::jsonSerialize();
        return array_merge($value, get_object_vars($this));
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
