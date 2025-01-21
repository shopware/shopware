<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\Adapter\Twig\Extension\BuildBreadcrumbExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(BuildBreadcrumbExtension::class)]
class BuildBreadcrumbExtensionTest extends TestCase
{
    public function testGetFunctions(): void
    {
        $functions = $this->getBuildBreadcrumbExtension()->getFunctions();

        static::assertSame('sw_breadcrumb_full', $functions[0]->getName());
        static::assertSame('sw_breadcrumb_full_by_id', $functions[1]->getName());
    }

    public function testGetFullBreadcrumbNoSeoBreadCrumb(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $breadCrumb = $this->getBuildBreadcrumbExtension()
            ->getFullBreadcrumb(['context' => $salesChannelContext], new CategoryEntity(), $salesChannelContext->getContext());

        static::assertSame([], $breadCrumb);
    }

    public function testGetFullBreadcrumbWithEmptySeoBreadCrumb(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $categoryBreadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $categoryBreadcrumbBuilder->method('build')->willReturn([]);

        $breadCrumb = $this->getBuildBreadcrumbExtension($categoryBreadcrumbBuilder)
            ->getFullBreadcrumb(['context' => $salesChannelContext], new CategoryEntity(), $salesChannelContext->getContext());

        static::assertSame([], $breadCrumb);
    }

    public function testGetFullBreadcrumbWithSeoBreadCrumb(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $categoryId = Uuid::randomHex();
        $notConsideredCategoryId = Uuid::randomHex();

        $categoryBreadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $categoryBreadcrumbBuilder->method('build')->willReturn([$categoryId => 'Home', $notConsideredCategoryId => 'Not considered']);

        $breadCrumb = $this->getBuildBreadcrumbExtension($categoryBreadcrumbBuilder, $categoryId)
            ->getFullBreadcrumb(['context' => $salesChannelContext], new CategoryEntity(), $salesChannelContext->getContext());

        static::assertArrayHasKey($categoryId, $breadCrumb);
        static::assertInstanceOf(CategoryEntity::class, $breadCrumb[$categoryId]);
        static::assertArrayNotHasKey($notConsideredCategoryId, $breadCrumb);
    }

    public function testGetFullBreadcrumbByIdWithNonExistingCategoryId(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $breadCrumb = $this->getBuildBreadcrumbExtension()
            ->getFullBreadcrumbById(['context' => $salesChannelContext], Uuid::randomHex(), $salesChannelContext->getContext());

        static::assertSame([], $breadCrumb);
    }

    public function testGetFullBreadcrumbById(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $categoryId = Uuid::randomHex();
        $notConsideredCategoryId = Uuid::randomHex();

        $categoryBreadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $categoryBreadcrumbBuilder->method('build')->willReturn([$categoryId => 'Home', $notConsideredCategoryId => 'Not considered']);

        $breadCrumb = $this->getBuildBreadcrumbExtension($categoryBreadcrumbBuilder, $categoryId)
            ->getFullBreadcrumbById(['context' => $salesChannelContext], $categoryId, $salesChannelContext->getContext());

        static::assertArrayHasKey($categoryId, $breadCrumb);
        static::assertInstanceOf(CategoryEntity::class, $breadCrumb[$categoryId]);
        static::assertArrayNotHasKey($notConsideredCategoryId, $breadCrumb);
    }

    private function getBuildBreadcrumbExtension(?CategoryBreadcrumbBuilder $categoryBreadcrumbBuilder = null, ?string $categoryId = null): BuildBreadcrumbExtension
    {
        $categoryBreadcrumbBuilder ??= $this->createMock(CategoryBreadcrumbBuilder::class);

        $categories = new CategoryCollection();
        if ($categoryId !== null) {
            $category = new CategoryEntity();
            $category->setUniqueIdentifier($categoryId);
            $categories->add($category);
        }

        $entitySearchResult = new EntitySearchResult(
            CategoryDefinition::ENTITY_NAME,
            1,
            $categories,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        /** @var StaticEntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = new StaticEntityRepository([
            $entitySearchResult, clone $entitySearchResult,
        ]);

        return new BuildBreadcrumbExtension($categoryBreadcrumbBuilder, $categoryRepository);
    }
}
