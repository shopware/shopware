<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Schema\AvailableDataMap;
use Shopware\Core\Framework\ContentSystem\Schema\AvailableDataResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemAvailableDataSchemaGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

#[CoversClass(ContentSystemAvailableDataSchemaGenerator::class)]
class ContentSystemAvailableDataSchemaGeneratorTest extends TestCase
{
    #[TestDox('getSchema returns structured JSON with sources and types')]
    public function testGetSchemaReturnsStructuredJson(): void
    {
        $map = new AvailableDataMap([
            'navigation' => [new ContentDataLoaderTypeDescriptor(Tree::class)],
        ]);

        $resolver = $this->createMock(AvailableDataResolver::class);
        $resolver->method('resolve')->willReturn($map);

        $generator = new ContentSystemAvailableDataSchemaGenerator($resolver);
        $schema = $generator->getSchema();

        static::assertArrayHasKey('sources', $schema);
        static::assertArrayHasKey('navigation', $schema['sources']);
        static::assertSame([['className' => Tree::class, 'genericParameters' => []]], $schema['sources']['navigation']['types']);
    }

    #[TestDox('getSchema includes genericParameters')]
    public function testGetSchemaIncludesGenericParameters(): void
    {
        $map = new AvailableDataMap([
            'product_review' => [new ContentDataLoaderTypeDescriptor(EntitySearchResult::class, [ProductReviewCollection::class])],
        ]);

        $resolver = $this->createMock(AvailableDataResolver::class);
        $resolver->method('resolve')->willReturn($map);

        $generator = new ContentSystemAvailableDataSchemaGenerator($resolver);
        $schema = $generator->getSchema();

        $type = $schema['sources']['product_review']['types'][0];
        static::assertSame(EntitySearchResult::class, $type['className']);
        static::assertSame([ProductReviewCollection::class], $type['genericParameters']);
    }
}
