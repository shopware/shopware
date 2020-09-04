<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
            new TwigFunction('sw_breadcrumb', [$this, 'buildSeoBreadcrumb'], ['needs_context' => true]),
        ];
    }

    public function buildSeoBreadcrumb(array $twigContext, CategoryEntity $category, ?string $navigationCategoryId = null): ?array
    {
        $salesChannel = null;
        if (array_key_exists('context', $twigContext) && $twigContext['context'] instanceof SalesChannelContext) {
            $salesChannel = $twigContext['context']->getSalesChannel();
        }

        return $this->categoryBreadcrumbBuilder->build($category, $salesChannel, $navigationCategoryId);
    }
}
