<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingSourceReference::class)]
class MappingSourceReferenceTest extends TestCase
{
    public function testBuildsAndDisplaysRootReferences(): void
    {
        $wholeValue = MappingSourceReference::root('product');
        $member = MappingSourceReference::fromRootPath('product.name');

        static::assertSame('product', $wholeValue->displayName());
        static::assertSame('product.name', $member->displayName());
        static::assertTrue($member->isSameAs(new MappingSourceReference('root', 'product', path: 'name')));
        static::assertSame(['type' => 'root', 'id' => 'product', 'path' => 'name'], $member->jsonSerialize());
    }

    public function testSerializesProviderReferencesWithOptionalConfiguration(): void
    {
        $source = new MappingSourceReference('plugin', 'example', ['region' => 'eu'], 'message');

        static::assertSame('plugin:example.message', $source->displayName());
        static::assertSame([
            'type' => 'plugin',
            'id' => 'example',
            'config' => ['region' => 'eu'],
            'path' => 'message',
        ], $source->jsonSerialize());
    }

    public function testParsesAReferenceArrayWithOptionalFieldsOmitted(): void
    {
        $source = MappingSourceReference::fromArray(['type' => 'root', 'id' => 'product'], 'source');

        static::assertSame(['type' => 'root', 'id' => 'product'], $source->jsonSerialize());
    }

    public function testRejectsUnknownFields(): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidFieldValueType('source', 'source fields type, id, config, path', 'unknown key extra'));

        MappingSourceReference::fromArray(['type' => 'root', 'id' => 'product', 'extra' => true], 'source');
    }

    public function testRejectsAnEmptyMemberPath(): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidFieldValueType('source.path', 'non-empty string', 'string'));

        MappingSourceReference::fromArray(['type' => 'root', 'id' => 'product', 'path' => ''], 'source');
    }
}
