<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\Simple;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Version;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    public function testCreateField(): void
    {
        $attribute = new Version();

        $field = $attribute->createField(
            'versionId',
            'version_id',
            'product'
        );

        static::assertInstanceOf(VersionField::class, $field);
        static::assertSame('versionId', $field->getPropertyName());
        static::assertSame('version_id', $field->getStorageName());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new Version();

        static::assertSame(VersionField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'nullable' => false,
            'type' => Version::TYPE,
            'translated' => false,
            'api' => true,
            'column' => null,
        ];

        $attribute = Version::fromArray($data);

        static::assertFalse($attribute->nullable);
        static::assertTrue($attribute->api);
    }

    public function testToDefinition(): void
    {
        $attribute = new Version();
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([Version::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(Version::TYPE, $args[0]['type']);
        static::assertFalse($args[0]['nullable']);
    }
}
