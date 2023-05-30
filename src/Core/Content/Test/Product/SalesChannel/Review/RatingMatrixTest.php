<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Review;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;

/**
 * @internal
 */
class RatingMatrixTest extends TestCase
{
    /**
     * check that constants are defined as expected
     *
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
     * @dataProvider getRatings
     *
     * @group reviews
     */
    public function testMatrixCalculation(float $expectedScore, int $reviewCounts, float $total, array $aggregation): void
    {
        $matrix = new RatingMatrix($aggregation);
        $expectedScore = round($expectedScore, 4);
        $actual = round($matrix->getAverageRating(), 4);
        static::assertEquals($expectedScore, $actual, 'expected score does not match');
        static::assertEquals($reviewCounts, $matrix->getTotalReviewCount(), 'expected total review count does not match');
        static::assertEquals($total, $matrix->getPointSum(), 'expected total review points does not match');
    }

    /**
     * dataprovider for testMatrixCalculation
     */
    public static function getRatings(): array
    {
        return [
            'all one, rating should be 3, review count 5 and total points 15' => [
                3.0, 5, 15, self::getDatabaseMatrixAggregationArray(1, 1, 1, 1, 1),
            ],
            'rating should be 3, review count 12 and total points 40' => [
                3.33333, 12, 40, self::getDatabaseMatrixAggregationArray(2, 1, 3, 3, 3),
            ],
            'all zero, rating should be 0, review count 0 and total points 0' => [
                0.0, 0, 0, self::getDatabaseMatrixAggregationArray(0, 0, 0, 0, 0),
            ],
            'only one point rating given, rating should be 5, review count 15 and total points 75' => [
                5.0, 15, 75, self::getDatabaseMatrixAggregationArray(0, 0, 0, 0, 15),
            ],
            'ascending numbers of floats, rating should be 4,0667, count 15, total 61' => [
                4.0667, 15, 61, self::getDatabaseMatrixAggregationArrayWithFloats(1, 2, 3, 4, 5),
            ],
        ];
    }

    /**
     * check that matrix calculates correctly
     *
     * @group reviews
     */
    public function testMatrixWithAllAtOne(): void
    {
        $oneCount = 1;
        $twoCount = 1;
        $threeCount = 1;
        $fourCount = 1;
        $fiveCount = 1;
        $aggregation = array_merge(
            self::getDatabaseMatrixAggregationArray($oneCount, $twoCount, $threeCount, $fourCount, $fiveCount),
            self::getDatabaseMatrixAggregationArrayWithFloats($oneCount, $twoCount, $threeCount, $fourCount, $fiveCount)
        );

        $matrix = new RatingMatrix($aggregation);
        $expectedRatingScore = 3.2;
        $expectedReviewCounts = 2 * ($oneCount + $twoCount + $threeCount + $fourCount + $fiveCount);
        $expectedTotal = 32;

        static::assertCount(5, $matrix->getMatrix());
        static::assertEquals($expectedRatingScore, $matrix->getAverageRating());
        static::assertEquals($expectedReviewCounts, $matrix->getTotalReviewCount());
        static::assertEquals($expectedTotal, $matrix->getPointSum());
    }

    /**
     * function tests that every MatrixElement struct is generated correctly by matrix constructor
     *
     * @group reviews
     */
    public function testMatrixElements(): void
    {
        $ratingCounts = [1 => 2, 2 => 3, 3 => 0, 4 => 4, 5 => 12];

        $aggregation = self::getDatabaseMatrixAggregationArray($ratingCounts[1], $ratingCounts[2], $ratingCounts[3], $ratingCounts[4], $ratingCounts[5]);

        $matrix = new RatingMatrix($aggregation);

        $totalReviews = $matrix->getTotalReviewCount();

        static::assertCount(5, $matrix->getMatrix());

        /** @var MatrixElement[] $matrixElements */
        $matrixElements = $matrix->getMatrix();

        for ($i = 1; $i <= $matrix->getMaxPoints(); ++$i) {
            $matrixElement = $matrixElements[$i];
            $expected = round(($ratingCounts[$i] * 100 / $totalReviews), 4);
            static::assertEquals($i, $matrixElement->getPoints(), sprintf('The rating with %d points has errors!', $i));
            static::assertEquals($ratingCounts[$i], $matrixElement->getCount(), sprintf('The count of reviews with %d points has errors!', $i));
            static::assertEquals($expected, round($matrixElement->getPercent(), 4), sprintf('Calculation of percentage with %d points has errors!', $i));
        }
    }

    /**
     * function tests that every MatrixElement struct is generated correctly by matrix constructor using float values
     *
     * @group reviews
     */
    public function testMatrixElementsWithFloatValues(): void
    {
        $ratingCounts = [1 => 2, 2 => 3, 3 => 3, 4 => 4, 5 => 12];
        $expectedCounts = [1 => 2, 2 => 3, 3 => 0, 4 => 3, 5 => 16];

        $aggregation = self::getDatabaseMatrixAggregationArrayWithFloats(
            $ratingCounts[1],
            $ratingCounts[2],
            $ratingCounts[3],
            $ratingCounts[4],
            $ratingCounts[5]
        );

        $matrix = new RatingMatrix($aggregation);

        $totalReviews = $matrix->getTotalReviewCount();

        static::assertCount(5, $matrix->getMatrix());

        /** @var MatrixElement[] $matrixElements */
        $matrixElements = $matrix->getMatrix();

        for ($i = 1; $i <= $matrix->getMaxPoints(); ++$i) {
            $matrixElement = $matrixElements[$i];
            $expected = round(($expectedCounts[$i] * 100 / $totalReviews), 4);
            static::assertEquals($i, $matrixElement->getPoints(), sprintf('The rating with %d points has errors!', $i));
            static::assertEquals($expectedCounts[$i], $matrixElement->getCount(), sprintf('The count of reviews with %d points has errors!', $i));
            static::assertEquals($expected, round($matrixElement->getPercent(), 4), sprintf('Calculation of percentage with %d points has errors!', $i));
        }
    }

    /**
     * check that matrix calculates correctly
     *
     * @group reviews
     */
    public function testMissingAggregations(): void
    {
        $stars = [
            new Bucket('1.2', 3, null),
            new Bucket('2.0', 3, null),
            new Bucket('4.0', 3, null),
        ];

        $matrix = new RatingMatrix($stars);

        static::assertCount(5, $matrix->getMatrix());
        static::assertEquals(2.4, round($matrix->getAverageRating(), 1));
        static::assertEquals(9, $matrix->getTotalReviewCount());
        static::assertEquals(21.6, $matrix->getPointSum());
    }

    /**
     * helper function for simulating dal aggregation result
     */
    private static function getDatabaseMatrixAggregationArray(int $oneCount, int $twoCount, int $threeCount, int $fourCount, int $fiveCount): array
    {
        return [
            new Bucket('1.0', $oneCount, null),
            new Bucket('2.0', $twoCount, null),
            new Bucket('3.0', $threeCount, null),
            new Bucket('4.0', $fourCount, null),
            new Bucket('5.0', $fiveCount, null),
        ];
    }

    /**
     * helper function for simulating dal aggregation result
     */
    private static function getDatabaseMatrixAggregationArrayWithFloats(int $oneCount, int $twoCount, int $threeCount, int $fourCount, int $fiveCount): array
    {
        return [
            new Bucket('1.2', $oneCount, null),
            new Bucket('2.4', $twoCount, null),
            new Bucket('3.6', $threeCount, null),
            new Bucket('4.8', $fourCount, null),
            new Bucket('5.0', $fiveCount, null),
        ];
    }
}
