<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NumberRangeField::class)]
class NumberRangeFieldTest extends TestCase
{
    public function testMaxLengthDefaultsToSixtyFour(): void
    {
        $field = new NumberRangeField('number', 'number');

        static::assertSame('number', $field->getStorageName());
        static::assertSame('number', $field->getPropertyName());
        static::assertSame(64, $field->getMaxLength());
    }

    public function testMaxLengthCanBeOverridden(): void
    {
        static::assertSame(32, (new NumberRangeField('number', 'number', 32))->getMaxLength());
    }
}
