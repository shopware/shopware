<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\Struct\Struct;

class RatingMatrix extends Struct
{
    private const MAX_POINTS = 5;

    /**
     * @var MatrixElement[]
     */
    protected $matrix = [];

    /**
     * @var int
     */
    protected $totalReviewCount = 0;

    /**
     * @var int
     */
    protected $totalPoints = 0;

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
                if ($points === (int) $rating->getKey()) {
                    $this->totalPoints += ($points * $rating->getCount());

                    $this->totalReviewCount += $rating->getCount();

                    $this->matrix[$points] = new MatrixElement($points, $rating->getCount());

                    break;
                }
            }

            if (!array_key_exists($points, $this->matrix)) {
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
     * returns all matrix elements
     */
    public function getMatrix(): array
    {
        return $this->matrix;
    }

    /**
     * returns how often a product has been rated
     */
    public function getTotalReviewCount(): int
    {
        return $this->totalReviewCount;
    }

    /**
     * returns the sum of the points of every rating
     */
    public function getTotalPoints(): int
    {
        return $this->totalPoints;
    }

    /**
     * returns constant value of MAX_POINTS
     */
    public function getMaxPoints(): int
    {
        return self::MAX_POINTS;
    }

    /**
     * returns average rating in points
     */
    public function getAverageRating(): float
    {
        if ($this->getTotalPoints() > 0) {
            return $this->totalPoints / $this->totalReviewCount;
        }

        return 0;
    }
}
