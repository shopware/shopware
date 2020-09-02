<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BuildBreadcrumbExtension extends AbstractExtension
{
    /**
     * @var CategoryBreadcrumbBuilder
     */
    private $categoryBreadcrumbBuilder;

    public function __construct(CategoryBreadcrumbBuilder $categoryBreadcrumbBuilder)
    {
        $this->categoryBreadcrumbBuilder = $categoryBreadcrumbBuilder;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_breadcrumb', [$this, 'buildSeoBreadcrumb'], ['is_safe' => ['html']]),
        ];
    }

    public function buildSeoBreadcrumb(CategoryEntity $category, ?SalesChannelEntity $salesChannel = null, ?string $navigationCategoryId = null): ?array
    {
        return $this->categoryBreadcrumbBuilder->build($category, $salesChannel, $navigationCategoryId);
    }
}
