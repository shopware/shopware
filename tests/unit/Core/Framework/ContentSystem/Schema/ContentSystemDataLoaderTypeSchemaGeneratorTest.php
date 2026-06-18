<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeSchemaGenerator;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeSchemaGenerator::class)]
class ContentSystemDataLoaderTypeSchemaGeneratorTest extends TestCase
{
    /**
     * @param list<array{producedType: string, configTemplate: array<string, mixed>, requiredConfigKeys: list<string>, genericParameters: list<string>}> $expectedTypes
     */
    #[DataProvider('buildsSchemaEntryProvider')]
    #[TestDox('builds schema entry for $_dataName')]
    public function testGetSchemaBuildsSourceEntry(string $source, LoaderTypeCapability $capability, array $expectedTypes): void
    {
        $map = new ContentSystemDataLoaderTypeMap([$source => [$capability]]);

        $resolver = static::createStub(AbstractContentSystemDataLoaderTypeResolver::class);
        $resolver->method('resolve')->willReturn($map);

        $generator = new ContentSystemDataLoaderTypeSchemaGenerator($resolver);
        $schema = $generator->getSchema();

        static::assertArrayHasKey('sources', $schema);
        static::assertSame($expectedTypes, $schema['sources'][$source]['types']);
    }

    /**
     * @return iterable<string, array{string, LoaderTypeCapability, list<array{producedType: string, configTemplate: array<string, mixed>, requiredConfigKeys: list<string>, genericParameters: list<string>}>}>
     */
    public static function buildsSchemaEntryProvider(): iterable
    {
        yield 'a fixed-type loader with empty template and no required keys' => [
            'navigation',
            new LoaderTypeCapability(Tree::class),
            [['producedType' => Tree::class, 'configTemplate' => [], 'requiredConfigKeys' => [], 'genericParameters' => []]],
        ];

        yield 'a wildcard collection loader with config template, required keys and generics' => [
            'entity_collection',
            new LoaderTypeCapability(SalesChannelProductCollection::class, ['entity' => 'product'], ['property'], [SalesChannelProductEntity::class]),
            [[
                'producedType' => SalesChannelProductCollection::class,
                'configTemplate' => ['entity' => 'product'],
                'requiredConfigKeys' => ['property'],
                'genericParameters' => [SalesChannelProductEntity::class],
            ]],
        ];
    }
}
