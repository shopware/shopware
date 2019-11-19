<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Product\Review;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Storefront\Page\Product\Review\MatrixElement;
use Shopware\Storefront\Page\Product\Review\RatingMatrix;

class RatingMatrixTest extends TestCase
{
    /**
     * check that constants are defined as expected
     *
     * @test
     * @group reviews
     */
    public function testThatConstantsAreSetLikeExpected(): void
    {
        $matrix = new RatingMatrix([]);
        static::assertEquals(5, $matrix->getMaxPoints());
    }

    /**
     * check that matrix calculates correctly
     *
     * @test
     * @dataProvider getRatings
     * @group reviews
     */
    public function testMatrixCalculation(float $expectedScore, int $reviewCounts, int $total, array $aggregation): void
    {
        $matrix = new RatingMatrix($aggregation);
        $expectedScore = round($expectedScore, 4);
        $actual = round($matrix->getAverageRating(), 4);
        static::assertEquals($expectedScore, $actual, 'expected Score does not match');
        static::assertEquals($reviewCounts, $matrix->getTotalReviewCount(), 'expected total review count does not match');
        static::assertEquals($total, $matrix->getTotalPoints(), 'expected total review points does not match');
    }

    /**
     * check that matrix calculates correctly
     *
     * @test
     * @group reviews
     */
    public function testMatrixWithAllAtOne(): void
    {
        $oneCount = 1;
        $twoCount = 1;
        $threeCount = 1;
        $fourCount = 1;
        $fiveCount = 1;
        $aggregation = $this->getDatabaseMatrixAggregationArray($oneCount, $twoCount, $threeCount, $fourCount, $fiveCount);

        $matrix = new RatingMatrix($aggregation);
        $expectedRatingScore = 3.0;
        $expectedReviewCounts = $oneCount + $twoCount + $threeCount + $fourCount + $fiveCount;
        $expectedTotal = 15;

        static::assertCount(5, $matrix->getMatrix());
        static::assertEquals($expectedRatingScore, $matrix->getAverageRating());
        static::assertEquals($expectedReviewCounts, $matrix->getTotalReviewCount());
        static::assertEquals($expectedTotal, $matrix->getTotalPoints());
    }

    /**
     * function tests that every MatrixElement struct is generated correctly by matrix constructor
     *
     * @test
     * @group reviews
     */
    public function testMatixELements(): void
    {
        $ratingCounts = [1 => 2, 2 => 3, 3 => 0, 4 => 4, 5 => 12];

        $aggregation = $this->getDatabaseMatrixAggregationArray($ratingCounts[1], $ratingCounts[2], $ratingCounts[3], $ratingCounts[4], $ratingCounts[5]);

        $matrix = new RatingMatrix($aggregation);

        $totalReviews = $matrix->getTotalReviewCount();

        static::assertCount(5, $matrix->getMatrix());

        /** @var MatrixElement[] $matrixElements */
        $matrixElements = $matrix->getMatrix();

        for ($i = 1; $i <= $matrix->getMaxPoints(); ++$i) {
            $matrixElement = $matrixElements[$i];
            $expected = round(($ratingCounts[$i] * 100 / $totalReviews), 4);
            static::assertEquals($i, $matrixElement->getPoints(), sprintf('The rating with %d point has errors !', $i));
            static::assertEquals($ratingCounts[$i], $matrixElement->getCount(), sprintf('The count of reviews with %d point has errors !', $i));
            static::assertEquals($expected, round($matrixElement->getPercent(), 4), sprintf('Calculation of percentage with %d point has errors !', $i));
        }
    }

    /**
     * check that matrix calculates correctly
     *
     * @test
     * @group reviews
     */
    public function testMissingAggregations(): void
    {
        $twoStars = new Bucket('2.0', 3, null);
        $threeStars = new Bucket('4.0', 3, null);

        $matrix = new RatingMatrix([$twoStars, $threeStars]);

        static::assertCount(5, $matrix->getMatrix());
        static::assertEquals(3.0, round($matrix->getAverageRating(), 1));
        static::assertEquals(6, $matrix->getTotalReviewCount());
        static::assertEquals(18, $matrix->getTotalPoints());
    }

    /**
     * dataprovider for testMatrixCalculation
     */
    public function getRatings(): array
    {
        return [
            'all one, rating should be 3, review count 5 and total points 15' => [3.0, 5, 15, $this->getDatabaseMatrixAggregationArray(1, 1, 1, 1, 1)],
            'rating should be 3, review count 12 and total points 40' => [3.33333, 12, 40, $this->getDatabaseMatrixAggregationArray(2, 1, 3, 3, 3)],
            'all zero, rating should be 0, review count 0 and total points 0' => [0.0, 0, 0, $this->getDatabaseMatrixAggregationArray(0, 0, 0, 0, 0)],
            'only one point rating given, rating should be 5, review count 15 and total points 75' => [5.0, 15, 75, $this->getDatabaseMatrixAggregationArray(0, 0, 0, 0, 15)],
        ];
    }

    /**
     * helper function for simulating dal aggregation result
     */
    private function getDatabaseMatrixAggregationArray(int $oneCount, int $twoCount, int $threeCount, int $fourCount, int $fiveCount): array
    {
        $oneStar = new Bucket('1.0', $oneCount, null);
        $twoStars = new Bucket('2.0', $twoCount, null);
        $threeStars = new Bucket('3.0', $threeCount, null);
        $fourStars = new Bucket('4.0', $fourCount, null);
        $fiveStars = new Bucket('5.0', $fiveCount, null);

        return [$oneStar, $twoStars, $threeStars, $fourStars, $fiveStars];
    }
}
