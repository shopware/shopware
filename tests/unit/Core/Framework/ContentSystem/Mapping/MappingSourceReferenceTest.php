<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @param array{type: string, id: string, config?: array<string, mixed>, path?: string} $source
     */
    #[DataProvider('displayNameExamples')]
    public function testFormatsSourceDisplayNames(array $source, string $expected): void
    {
        static::assertSame($expected, MappingSourceReference::fromArray($source, 'source')->displayName());
    }

    /**
     * Mirrored in Administration's element-mapping.util.spec/source-display-name.spec.ts.
     *
     * @return iterable<string, array{array{type: string, id: string, config?: array<string, mixed>, path?: string}, string}>
     */
    public static function displayNameExamples(): iterable
    {
        yield 'whole root source' => [['type' => 'root', 'id' => 'product'], 'product'];
        yield 'root member' => [['type' => 'root', 'id' => 'product', 'path' => 'name'], 'product.name'];
        yield 'nested root member' => [['type' => 'root', 'id' => 'product', 'path' => 'manufacturer.name'], 'product.manufacturer.name'];
        yield 'whole plugin source' => [['type' => 'AcmePlugin', 'id' => 'recommendations'], 'AcmePlugin:recommendations'];
        yield 'plugin member' => [['type' => 'AcmePlugin', 'id' => 'recommendations', 'path' => 'title'], 'AcmePlugin:recommendations.title'];
        yield 'nested plugin member' => [['type' => 'AcmePlugin', 'id' => 'recommendations', 'path' => 'metadata.title'], 'AcmePlugin:recommendations.metadata.title'];
        yield 'configuration does not change the name' => [['type' => 'AcmePlugin', 'id' => 'recommendations', 'config' => ['region' => 'eu'], 'path' => 'title'], 'AcmePlugin:recommendations.title'];
    }

    public function testBuildsAndDisplaysRootReferences(): void
    {
        $wholeValue = MappingSourceReference::root('product');
        $member = MappingSourceReference::fromRootPath('product.name');

        static::assertSame('product', $wholeValue->displayName());
        static::assertSame('product.name', $member->displayName());
        static::assertTrue($member->isSameAs(new MappingSourceReference('root', 'product', path: 'name')));
        static::assertSame(['type' => 'root', 'id' => 'product', 'path' => 'name'], $member->jsonSerialize());
    }

    /**
     * @param array<string, mixed> $first
     * @param array<string, mixed> $second
     */
    #[DataProvider('sourceIdentityExamples')]
    public function testComparesSourceIdentityWithoutDependingOnMapKeyOrder(array $first, array $second, bool $expected): void
    {
        $source = MappingSourceReference::fromArray($first, 'source');
        $other = MappingSourceReference::fromArray($second, 'source');

        static::assertSame($expected, $source->isSameAs($other));
        static::assertSame($expected, $other->isSameAs($source));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, bool}>
     */
    public static function sourceIdentityExamples(): iterable
    {
        $source = ['type' => 'AcmePlugin', 'id' => 'recommendations', 'path' => 'title'];

        yield 'reordered configuration keys' => [
            [...$source, 'config' => ['country' => 'DE', 'currency' => 'EUR']],
            [...$source, 'config' => ['currency' => 'EUR', 'country' => 'DE']],
            true,
        ];
        yield 'reordered nested keys inside a list' => [
            [...$source, 'config' => ['filters' => [['country' => 'DE', 'limit' => 5]]]],
            [...$source, 'config' => ['filters' => [['limit' => 5, 'country' => 'DE']]]],
            true,
        ];
        yield 'omitted and empty configuration' => [$source, [...$source, 'config' => []], true];
        yield 'identical ordered list' => [
            [...$source, 'config' => ['priority' => ['featured', 'new']]],
            [...$source, 'config' => ['priority' => ['featured', 'new']]],
            true,
        ];
        yield 'reordered list remains different' => [
            [...$source, 'config' => ['priority' => ['featured', 'new']]],
            [...$source, 'config' => ['priority' => ['new', 'featured']]],
            false,
        ];
        yield 'list and numeric map remain different' => [
            [...$source, 'config' => ['priority' => ['featured', 'new']]],
            [...$source, 'config' => ['priority' => [1 => 'new', 0 => 'featured']]],
            false,
        ];
        yield 'changed nested value' => [
            [...$source, 'config' => ['options' => ['limit' => 5]]],
            [...$source, 'config' => ['options' => ['limit' => 6]]],
            false,
        ];
        yield 'integer and string remain different' => [
            [...$source, 'config' => ['limit' => 5]],
            [...$source, 'config' => ['limit' => '5']],
            false,
        ];
        yield 'integer and float remain different' => [
            [...$source, 'config' => ['limit' => 5]],
            [...$source, 'config' => ['limit' => 5.0]],
            false,
        ];
        yield 'boolean and integer remain different' => [
            [...$source, 'config' => ['enabled' => false]],
            [...$source, 'config' => ['enabled' => 0]],
            false,
        ];
        yield 'missing and null settings remain different' => [
            $source,
            [...$source, 'config' => ['limit' => null]],
            false,
        ];
        yield 'additional settings remain different' => [
            [...$source, 'config' => ['country' => 'DE']],
            [...$source, 'config' => ['country' => 'DE', 'limit' => 5]],
            false,
        ];
        yield 'different source type' => [$source, [...$source, 'type' => 'OtherPlugin'], false];
        yield 'different source identifier' => [$source, [...$source, 'id' => 'other'], false];
        yield 'different member path' => [$source, [...$source, 'path' => 'description'], false];
        yield 'whole source and member remain different' => [
            ['type' => 'AcmePlugin', 'id' => 'recommendations'],
            $source,
            false,
        ];
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
