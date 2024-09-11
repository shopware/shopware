<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement;

/**
 * @internal
 */
#[CoversClass(MatrixElement::class)]
class MatrixElementTest extends TestCase
{
    private MatrixElement $element;

    protected function setUp(): void
    {
        $this->element = new MatrixElement(1, 1, 0.3);
    }

    #[Group('reviews')]
    public function testConstructor(): void
    {
        $points = 2;
        $count = 3;
        $percent = 1.0;

        $element = new MatrixElement($points, $count, $percent);

        static::assertEquals($points, $element->getPoints());
        static::assertEquals($count, $element->getCount());
        static::assertEquals($percent, $element->getPercent());
    }

    /**
     * test point getter and setter
     */
    #[Group('reviews')]
    public function testPointsGetterSetter(): void
    {
        $expected = 2;
        $this->element->setPoints($expected);

        static::assertEquals($expected, $this->element->getPoints());
    }

    /**
     * test count getter and setter
     */
    #[Group('reviews')]
    public function testCountGetterSetter(): void
    {
        $expected = 2;
        $this->element->setCount($expected);

        static::assertEquals($expected, $this->element->getCount());
    }

    /**
     * test percent getter and setter
     */
    #[Group('reviews')]
    public function testPercentGetterSetter(): void
    {
        $expected = 0.35;
        $this->element->setPercent($expected);

        static::assertEquals($expected, $this->element->getPercent());
    }
}
