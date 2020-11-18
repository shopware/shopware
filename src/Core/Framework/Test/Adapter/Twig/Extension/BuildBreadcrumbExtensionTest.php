<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\Adapter\Twig\Extension\BuildBreadcrumbExtension;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class BuildBreadcrumbExtensionTest extends TestCase
{
    public function breadCrumbDataProvider()
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

        $a = new BuildBreadcrumbExtension($service);
        $a->buildSeoBreadcrumb(['context' => $context], $category, $navigationCategoryId);
    }
}
