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
    #[TestDox('is empty when constructed without values')]
    public function testIsEmptyByDefault(): void
    {
        static::assertTrue((new ElementStyle())->isEmpty());
    }

    #[TestDox('is not empty when constructed with values')]
    public function testIsNotEmptyWithValues(): void
    {
        static::assertFalse((new ElementStyle(['col-span' => ['md' => 6]]))->isEmpty());
    }
}
