<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\Simple;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\AutoIncrement;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AutoIncrement::class)]
final class AutoIncrementTest extends TestCase
{
    public function testCreateField(): void
    {
        $attribute = new AutoIncrement();

        $field = $attribute->createField(
            'autoIncrement',
            'auto_increment',
            'product'
        );

        static::assertInstanceOf(AutoIncrementField::class, $field);
        static::assertSame('autoIncrement', $field->getPropertyName());
        static::assertSame('auto_increment', $field->getStorageName());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new AutoIncrement();

        static::assertSame(AutoIncrementField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'nullable' => false,
            'type' => AutoIncrement::TYPE,
            'translated' => false,
            'api' => true,
            'column' => null,
        ];

        $attribute = AutoIncrement::fromArray($data);

        static::assertFalse($attribute->nullable);
        static::assertTrue($attribute->api);
    }

    public function testToDefinition(): void
    {
        $attribute = new AutoIncrement();
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([AutoIncrement::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(AutoIncrement::TYPE, $args[0]['type']);
        static::assertFalse($args[0]['nullable']);
    }
}
