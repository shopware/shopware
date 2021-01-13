<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\Adapter\Twig\Extension\BuildBreadcrumbExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class BuildBreadcrumbExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var TestDataCollection
     */
    private $idCollection;

    public function setUp(): void
    {
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->idCollection = new TestDataCollection();
    }

    public function breadCrumbDataProvider(): array
    {
        return [
            [
                null,
                null,
            ],
            [
                $this->createMock(SalesChannelEntity::class),
                null,
            ],
            [
                null,
                Uuid::randomHex(),
            ],
            [
                $this->createMock(SalesChannelEntity::class),
                Uuid::randomHex(),
            ],
        ];
    }

    /**
     * @dataProvider breadCrumbDataProvider
     */
    public function testSwBreadcrumb($salesChannelEntity, $navigationCategoryId): void
    {
        $category = $this->createMock(CategoryEntity::class);

        $context = null;
        if ($salesChannelEntity !== null) {
            $context = $this->createMock(SalesChannelContext::class);
            $context->method('getSalesChannel')->willReturn($salesChannelEntity);
        }

        $service = $this->createMock(CategoryBreadcrumbBuilder::class);
        $service->expects(static::once())->method('build')->with($category, $salesChannelEntity, $navigationCategoryId);

        $repository = $this->createMock(EntityRepositoryInterface::class);

        $extension = new BuildBreadcrumbExtension($service, $repository);
        $extension->buildSeoBreadcrumb(['context' => $context], $category, $navigationCategoryId);
    }

    public function testSwBreadcrumbTypes(): void
    {
        $this->categoryRepository->create([
            [
                'id' => $this->idCollection->create('c1'),
                'name' => 'Category 1',
                'type' => 'folder',
                'children' => [
                    [
                        'id' => $this->idCollection->create('c2'),
                        'name' => 'Category 2',
                        'type' => 'page',
                        'children' => [
                            [
                                'id' => $this->idCollection->create('c3'),
                                'name' => 'Category 3',
                                'type' => 'link',
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $service = $this->createMock(CategoryBreadcrumbBuilder::class);
        $extension = new BuildBreadcrumbExtension($service, $this->categoryRepository);

        $types = $extension->getCategoryTypes($this->idCollection->all(), Context::createDefaultContext());

        static::assertSame('folder', $types[$this->idCollection->get('c1')]);
        static::assertSame('page', $types[$this->idCollection->get('c2')]);
        static::assertSame('link', $types[$this->idCollection->get('c3')]);
        static::assertCount(0, $extension->getCategoryTypes([], Context::createDefaultContext()));
        static::assertCount(0, $extension->getCategoryTypes(['1231231231231231'], Context::createDefaultContext()));
    }
}
