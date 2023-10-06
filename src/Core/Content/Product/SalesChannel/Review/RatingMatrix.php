<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class RatingMatrix extends Struct
{
    private const MAX_POINTS = 5;

    /**
     * @var MatrixElement[]
     */
    protected array $matrix = [];

    protected int $totalReviewCount = 0;

    protected float $pointSum = 0;

    /**
     * we expect an array of CountResult elements
     * we are doing all calculation in constructor
     *
     * @param Bucket[] $matrix
     */
    public function __construct(array $matrix)
    {
        for ($points = self::MAX_POINTS; $points > 0; --$points) {
            foreach ($matrix as $rating) {
                $rawRatingKey = (float) $rating->getKey();

                if ($points === (int) round($rawRatingKey)) {
                    $this->pointSum += ($rawRatingKey * $rating->getCount());

                    $this->totalReviewCount += $rating->getCount();

                    $previousCount = isset($this->matrix[$points]) ? $this->matrix[$points]->getCount() : 0;
                    $this->matrix[$points] = new MatrixElement($points, $rating->getCount() + $previousCount);
                }
            }

            if (!\array_key_exists($points, $this->matrix)) {
                $this->matrix[$points] = new MatrixElement($points, 0);
            }
        }

        array_walk($this->matrix, function (MatrixElement &$rating): void {
            if ($this->totalReviewCount > 0) {
                $rating->setPercent($rating->getCount() / $this->totalReviewCount * 100);
            } else {
                $rating->setPercent(0);
            }
        });
    }

    /**
     * @return MatrixElement[]
     */
    public function getMatrix(): array
    {
        return $this->matrix;
    }

    public function getTotalReviewCount(): int
    {
        return $this->totalReviewCount;
    }

    public function getPointSum(): float
    {
        return $this->pointSum;
    }

    public function getMaxPoints(): int
    {
        return self::MAX_POINTS;
    }

    public function getAverageRating(): float
    {
        if ($this->getPointSum() > 0) {
            return $this->getPointSum() / $this->getTotalReviewCount();
        }

        return 0;
    }
}
