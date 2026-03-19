<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeSchemaGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeSchemaGenerator::class)]
class ContentSystemDataLoaderTypeSchemaGeneratorTest extends TestCase
{
    /**
     * @param list<array{className: string, genericParameters: list<string>}> $expectedTypes
     */
    #[DataProvider('buildsSchemaEntryProvider')]
    #[TestDox('builds schema entry for $_dataName')]
    public function testGetSchemaBuildSourceEntry(string $source, ContentSystemDataLoaderTypeDescriptor $descriptor, array $expectedTypes): void
    {
        $map = new ContentSystemDataLoaderTypeMap([$source => [$descriptor]]);

        $resolver = static::createStub(AbstractContentSystemDataLoaderTypeResolver::class);
        $resolver->method('resolve')->willReturn($map);

        $generator = new ContentSystemDataLoaderTypeSchemaGenerator($resolver);
        $schema = $generator->getSchema();

        static::assertArrayHasKey('sources', $schema);
        static::assertSame($expectedTypes, $schema['sources'][$source]['types']);
    }

    /**
     * @return iterable<string, array{string, ContentSystemDataLoaderTypeDescriptor, list<array{className: string, genericParameters: list<string>}>}>
     */
    public static function buildsSchemaEntryProvider(): iterable
    {
        yield 'simple type without generic parameters' => [
            'navigation',
            new ContentSystemDataLoaderTypeDescriptor(Tree::class),
            [['className' => Tree::class, 'genericParameters' => []]],
        ];

        yield 'type with generic parameters' => [
            'product_review',
            new ContentSystemDataLoaderTypeDescriptor(EntitySearchResult::class, [ProductReviewCollection::class]),
            [['className' => EntitySearchResult::class, 'genericParameters' => [ProductReviewCollection::class]]],
        ];
    }
}
