<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 use Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement instead
 */
class MatrixElement
{
    /**
     * @var int
     */
    protected $points;

    /**
     * @var int
     */
    protected $count;

    /**
     * @var float
     */
    protected $percent;

    public function __construct(int $points, int $count, float $percent = 0.0)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement')
        );

        $this->points = $points;
        $this->count = $count;
        $this->percent = $percent;
    }

    public function getPoints(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement')
        );

        return $this->points;
    }

    public function setPoints(int $points): MatrixElement
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement')
        );

        $this->points = $points;

        return $this;
    }

    public function getCount(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement')
        );

        return $this->count;
    }

    public function setCount(int $count): MatrixElement
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement')
        );

        $this->count = $count;

        return $this;
    }

    public function getPercent(): float
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement')
        );

        return $this->percent;
    }

    public function setPercent(float $percent): MatrixElement
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement')
        );

        $this->percent = $percent;

        return $this;
    }
}
