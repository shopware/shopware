<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Product\Review;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Product\Review\MatrixElement;

class MatrixElementTest extends TestCase
{
    /**
     * @var MatrixElement
     */
    private $element;

    public function setUp(): void
    {
        $this->element = new MatrixElement(1, 1, 0.3);
    }

    /**
     * @test
     * @group reviews
     */
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
     *
     * @test
     * @group reviews
     */
    public function testPointsGetterSetter(): void
    {
        $expected = 2;
        $this->element->setPoints($expected);

        static::assertEquals($expected, $this->element->getPoints());
    }

    /**
     * test count getter and setter
     *
     * @test
     * @group reviews
     */
    public function testCountGetterSetter(): void
    {
        $expected = 2;
        $this->element->setCount($expected);

        static::assertEquals($expected, $this->element->getCount());
    }

    /**
     * test percent getter and setter
     *
     * @test
     * @group reviews
     */
    public function testPercentGetterSetter(): void
    {
        $expected = 0.35;
        $this->element->setPercent($expected);

        static::assertEquals($expected, $this->element->getPercent());
    }
}
