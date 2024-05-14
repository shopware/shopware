<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ReviewSummaryStruct extends StoreStruct
{
    /**
     * @var list<array{rating: int<1, 5>, count: positive-int}>
     */
    protected $ratingAssignment;

    /**
     * @var float
     */
    protected $averageRating;

    /**
     * @var int
     */
    protected $numberOfRatings;

    /**
     * @return ReviewSummaryStruct
     */
    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
    }

    /**
     * @return list<array{rating: int<1, 5>, count: positive-int}>
     */
    public function getRatingAssignment(): array
    {
        return $this->ratingAssignment;
    }

    /**
     * @param list<array{rating: int<1, 5>, count: positive-int}> $ratingAssignment
     */
    public function setRatingAssignment(array $ratingAssignment): void
    {
        $this->ratingAssignment = $ratingAssignment;
    }

    public function getAverageRating(): float
    {
        return $this->averageRating;
    }

    public function setAverageRating(float $averageRating): void
    {
        $this->averageRating = $averageRating;
    }

    public function getNumberOfRatings(): int
    {
        return $this->numberOfRatings;
    }

    public function setNumberOfRatings(int $numberOfRatings): void
    {
        $this->numberOfRatings = $numberOfRatings;
    }
}
