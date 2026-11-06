<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElementStyle::class)]
class ElementStyleTest extends TestCase
{
    #[TestDox('is not empty when constructed with values')]
    public function testIsNotEmptyWithValues(): void
    {
        static::assertFalse((new ElementStyle(['col-span' => ['md' => 6]]))->isEmpty());
    }

    #[TestDox('exposes style values for template access')]
    public function testGetValuesReturnsConfiguredStyleMap(): void
    {
        $values = ['col-span' => ['lg' => 6, 'xl' => 8]];

        static::assertSame($values, (new ElementStyle($values))->getValues());
        static::assertSame($values, (new ElementStyle($values))->toArray());
    }
}
