<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

#[CoversClass(ContentSystemDataLoaderTypeDescriptor::class)]
class ContentSystemDataLoaderTypeDescriptorTest extends TestCase
{
    #[TestDox('constructs with className only')]
    public function testConstructsWithClassNameOnly(): void
    {
        $descriptor = new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class);

        static::assertSame(ProductEntity::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
    }

    #[TestDox('constructs with className and genericParameters')]
    public function testConstructsWithGenericParameters(): void
    {
        $descriptor = new ContentSystemDataLoaderTypeDescriptor(
            EntitySearchResult::class,
            ['Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection'],
        );

        static::assertSame(EntitySearchResult::class, $descriptor->className);
        static::assertSame(['Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection'], $descriptor->genericParameters);
    }
}
