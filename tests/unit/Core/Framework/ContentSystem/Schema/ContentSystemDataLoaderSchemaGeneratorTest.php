<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemDataLoaderSchemaGenerator::class)]
class ContentSystemDataLoaderSchemaGeneratorTest extends TestCase
{
    /**
     * @param list<LoaderTypeCapability> $capabilities
     * @param list<array{producedType: string, configTemplate: array<string, mixed>, genericParameters: list<string>}> $expectedTypes
     */
    #[DataProvider('buildsTypesEntryProvider')]
    #[TestDox('builds types entry for $_dataName')]
    public function testGetSchemaBuildsTypesEntry(string $source, array $capabilities, array $expectedTypes): void
    {
        $map = new ContentSystemDataLoaderMap([$source => $capabilities], [$source => new LoaderConfigSpecification([])]);
        $generator = new ContentSystemDataLoaderSchemaGenerator($this->resolverReturning($map));

        $schema = $generator->getSchema();

        static::assertSame($expectedTypes, $schema['sources'][$source]['types']);
    }

    /**
     * @return iterable<string, array{string, list<LoaderTypeCapability>, list<array{producedType: string, configTemplate: array<string, mixed>, genericParameters: list<string>}>}>
     */
    public static function buildsTypesEntryProvider(): iterable
    {
        yield 'a fixed-type loader with an empty template' => [
            'navigation',
            [new LoaderTypeCapability(Tree::class)],
            [['producedType' => Tree::class, 'configTemplate' => [], 'genericParameters' => []]],
        ];

        yield 'a wildcard collection loader with a config template and generics' => [
            'entity_collection',
            [new LoaderTypeCapability(SalesChannelProductCollection::class, ['entity' => 'product'], [SalesChannelProductEntity::class])],
            [[
                'producedType' => SalesChannelProductCollection::class,
                'configTemplate' => ['entity' => 'product'],
                'genericParameters' => [SalesChannelProductEntity::class],
            ]],
        ];

        yield 'a source declaring multiple capabilities emits an entry per capability' => [
            'entity_collection',
            [
                new LoaderTypeCapability(SalesChannelProductCollection::class, ['entity' => 'product'], [SalesChannelProductEntity::class]),
                new LoaderTypeCapability(MediaCollection::class, ['entity' => 'media'], [MediaEntity::class]),
            ],
            [
                [
                    'producedType' => SalesChannelProductCollection::class,
                    'configTemplate' => ['entity' => 'product'],
                    'genericParameters' => [SalesChannelProductEntity::class],
                ],
                [
                    'producedType' => MediaCollection::class,
                    'configTemplate' => ['entity' => 'media'],
                    'genericParameters' => [MediaEntity::class],
                ],
            ],
        ];
    }

    public function testGetSchemaOutputsBothConfigKeysAndTypesFacetsPerSource(): void
    {
        $map = new ContentSystemDataLoaderMap(
            ['entity' => [new LoaderTypeCapability(MediaEntity::class, ['entity' => 'media'])]],
            ['entity' => new LoaderConfigSpecification([
                new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            ])],
        );
        $generator = new ContentSystemDataLoaderSchemaGenerator($this->resolverReturning($map));

        $schema = $generator->getSchema();

        static::assertSame(['configKeys', 'types'], array_keys($schema['sources']['entity']));
    }

    /**
     * @param list<array{name: string, kind: string, type: string, required: bool, default?: mixed, adminUI?: array<string, mixed>}> $expectedConfigKeys
     */
    #[DataProvider('buildsConfigKeyEntryProvider')]
    #[TestDox('builds config key entry for $_dataName')]
    public function testGetSchemaBuildsConfigKeyEntry(ConfigKeySpecification $key, array $expectedConfigKeys): void
    {
        $map = new ContentSystemDataLoaderMap(
            ['navigation' => []],
            ['navigation' => new LoaderConfigSpecification([$key])],
        );
        $generator = new ContentSystemDataLoaderSchemaGenerator($this->resolverReturning($map));

        $schema = $generator->getSchema();

        static::assertSame($expectedConfigKeys, $schema['sources']['navigation']['configKeys']);
    }

    /**
     * @return iterable<string, array{ConfigKeySpecification, list<array{name: string, kind: string, type: string, required: bool, default?: mixed, adminUI?: array<string, mixed>}>}>
     */
    public static function buildsConfigKeyEntryProvider(): iterable
    {
        yield 'a required entityName key with no default' => [
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            [['name' => 'entity', 'kind' => 'entityName', 'type' => 'string', 'required' => true]],
        ];

        yield 'a required propertyReference key with no default' => [
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            [['name' => 'property', 'kind' => 'propertyReference', 'type' => 'string', 'required' => true]],
        ];

        yield 'an optional literal key with a non-null default' => [
            new ConfigKeySpecification('depth', ConfigKeyKind::Literal, 'integer', required: false, hasDefault: true, default: 2),
            [['name' => 'depth', 'kind' => 'literal', 'type' => 'integer', 'required' => false, 'default' => 2]],
        ];

        yield 'an optional key with a declared null default' => [
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: null),
            [['name' => 'rootId', 'kind' => 'literal', 'type' => 'string', 'required' => false, 'default' => null]],
        ];

        yield 'a key with an adminUI hint' => [
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: null, adminUI: ['component' => 'select']),
            [['name' => 'rootId', 'kind' => 'literal', 'type' => 'string', 'required' => false, 'default' => null, 'adminUI' => ['component' => 'select']]],
        ];
    }

    public function testGetSchemaPreservesConfigKeyDeclarationOrder(): void
    {
        $keys = [
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ];
        $map = new ContentSystemDataLoaderMap(['entity' => []], ['entity' => new LoaderConfigSpecification($keys)]);
        $generator = new ContentSystemDataLoaderSchemaGenerator($this->resolverReturning($map));

        $schema = $generator->getSchema();

        static::assertSame(['entity', 'property', 'associations'], array_column($schema['sources']['entity']['configKeys'], 'name'));
    }

    public function testGetSchemaEmitsEmptyConfigKeysForAConfigLessSpecification(): void
    {
        $map = new ContentSystemDataLoaderMap(
            ['language' => [new LoaderTypeCapability(Tree::class)]],
            ['language' => new LoaderConfigSpecification([])],
        );
        $generator = new ContentSystemDataLoaderSchemaGenerator($this->resolverReturning($map));

        $schema = $generator->getSchema();

        static::assertSame([], $schema['sources']['language']['configKeys']);
    }

    private function resolverReturning(ContentSystemDataLoaderMap $map): AbstractContentSystemDataLoaderMapResolver
    {
        $resolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $resolver->method('resolve')->willReturn($map);

        return $resolver;
    }
}
