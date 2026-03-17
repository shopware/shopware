<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
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
    #[TestDox('returns structured JSON schema with sources and types')]
    public function testGetSchemaReturnsStructuredJson(): void
    {
        $map = new ContentSystemDataLoaderTypeMap([
            'navigation' => [new ContentSystemDataLoaderTypeDescriptor(Tree::class)],
        ]);

        $resolver = static::createStub(AbstractContentSystemDataLoaderTypeResolver::class);
        $resolver->method('resolve')->willReturn($map);

        $generator = new ContentSystemDataLoaderTypeSchemaGenerator($resolver);
        $schema = $generator->getSchema();

        static::assertArrayHasKey('sources', $schema);
        static::assertArrayHasKey('navigation', $schema['sources']);
        static::assertSame([['className' => Tree::class, 'genericParameters' => []]], $schema['sources']['navigation']['types']);
    }

    #[TestDox('includes genericParameters in schema')]
    public function testGetSchemaIncludesGenericParameters(): void
    {
        $map = new ContentSystemDataLoaderTypeMap([
            'product_review' => [new ContentSystemDataLoaderTypeDescriptor(EntitySearchResult::class, [ProductReviewCollection::class])],
        ]);

        $resolver = static::createStub(AbstractContentSystemDataLoaderTypeResolver::class);
        $resolver->method('resolve')->willReturn($map);

        $generator = new ContentSystemDataLoaderTypeSchemaGenerator($resolver);
        $schema = $generator->getSchema();

        $type = $schema['sources']['product_review']['types'][0];
        static::assertSame(EntitySearchResult::class, $type['className']);
        static::assertSame([ProductReviewCollection::class], $type['genericParameters']);
    }
}
