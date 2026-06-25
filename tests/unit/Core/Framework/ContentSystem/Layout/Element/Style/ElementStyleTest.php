<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;

/**
 * @internal
 */
#[CoversClass(ElementStyle::class)]
class ElementStyleTest extends TestCase
{
    #[TestDox('an element style without values is empty')]
    public function testIsEmptyByDefault(): void
    {
        static::assertTrue((new ElementStyle())->isEmpty());
    }

    #[TestDox('an element style carrying values is not empty')]
    public function testIsNotEmptyWithValues(): void
    {
        static::assertFalse((new ElementStyle(['col-span' => ['md' => 6]]))->isEmpty());
    }

    #[TestDox('toArray returns the option-to-breakpoint value map verbatim')]
    public function testToArrayReturnsValues(): void
    {
        $values = [
            'col-span' => ['md' => 6, 'lg' => 4],
            'display' => ['xs' => false],
        ];

        static::assertSame($values, (new ElementStyle($values))->toArray());
    }
}
