<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\Translations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Translations::class)]
final class TranslationsTest extends TestCase
{
    public function testCreateField(): void
    {
        $attribute = new Translations();

        $field = $attribute->createField(
            'translations',
            'translations',
            'product'
        );

        static::assertInstanceOf(TranslationsAssociationField::class, $field);
        static::assertSame('translations', $field->getPropertyName());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new Translations();

        static::assertSame(TranslationsAssociationField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'nullable' => false,
            'type' => Translations::TYPE,
            'translated' => false,
            'api' => true,
            'column' => null,
        ];

        $attribute = Translations::fromArray($data);

        static::assertFalse($attribute->nullable);
        static::assertNull($attribute->column);
    }

    public function testIsAlwaysApiVisible(): void
    {
        $attribute = new Translations();

        static::assertTrue($attribute->api);
    }

    public function testToDefinition(): void
    {
        $attribute = new Translations();
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([Translations::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(Translations::TYPE, $args[0]['type']);
        static::assertTrue($args[0]['api']);
        static::assertFalse($args[0]['nullable']);
    }
}
