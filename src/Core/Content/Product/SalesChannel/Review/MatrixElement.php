<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class MatrixElement
{
    public function __construct(
        protected int $points,
        protected int $count,
        protected float $percent = 0.0
    ) {
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): MatrixElement
    {
        $this->points = $points;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): MatrixElement
    {
        $this->count = $count;

        return $this;
    }

    public function getPercent(): float
    {
        return $this->percent;
    }

    public function setPercent(float $percent): MatrixElement
    {
        $this->percent = $percent;

        return $this;
    }
}
