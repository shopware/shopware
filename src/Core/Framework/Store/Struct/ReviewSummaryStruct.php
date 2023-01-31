<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class ReviewSummaryStruct extends StoreStruct
{
    /**
     * @var array
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

    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
    }

    public function getRatingAssignment(): array
    {
        return $this->ratingAssignment;
    }

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
