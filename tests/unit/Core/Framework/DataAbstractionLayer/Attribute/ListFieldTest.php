<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ListField::class)]
class ListFieldTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $field = new ListField();

        static::assertSame(ListField::TYPE, $field->type);
        static::assertNull($field->fieldType);
        static::assertFalse($field->api);
        static::assertFalse($field->translated);
        static::assertNull($field->column);
    }

    public function testConstructorPassesValuesToTheParent(): void
    {
        $field = new ListField(fieldType: IdField::class, api: true, translated: true, column: 'ids');

        static::assertSame(ListField::TYPE, $field->type);
        static::assertSame(IdField::class, $field->fieldType);
        static::assertTrue($field->api);
        static::assertTrue($field->translated);
        static::assertSame('ids', $field->column);
    }
}
