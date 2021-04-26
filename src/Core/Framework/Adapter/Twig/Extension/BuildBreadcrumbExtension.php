<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BuildBreadcrumbExtension extends AbstractExtension
{
    /**
     * @var CategoryBreadcrumbBuilder
     */
    private $categoryBreadcrumbBuilder;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    public function __construct(CategoryBreadcrumbBuilder $categoryBreadcrumbBuilder, EntityRepositoryInterface $categoryRepository)
    {
        $this->categoryBreadcrumbBuilder = $categoryBreadcrumbBuilder;
        $this->categoryRepository = $categoryRepository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_breadcrumb_full', [$this, 'getFullBreadcrumb'], ['needs_context' => true]),

            /*
             * @deprecated tag:v6.5.0 - Will be deleted, use sw_breadcrumb_categories instead.
             */
            new TwigFunction('sw_breadcrumb', [$this, 'buildSeoBreadcrumb'], ['needs_context' => true]),

            /*
             * @deprecated tag:v6.5.0 - Will be deleted, use sw_breadcrumb_categories instead.
             */
            new TwigFunction('sw_breadcrumb_types', [$this, 'getCategoryTypes']),

            /*
             * @deprecated tag:v6.5.0 - Will be deleted, without a replacement.
             */
            new TwigFunction('sw_breadcrumb_build_types', [$this, 'buildCategoryTypes']),
        ];
    }

    public function getFullBreadcrumb(array $twigContext, CategoryEntity $category, Context $context): array
    {
        $seoBreadcrumb = $this->buildSeoBreadcrumb($twigContext, $category);

        if ($seoBreadcrumb === null) {
            return [];
        }

        $categoryIds = array_keys($seoBreadcrumb);
        if (empty($categoryIds)) {
            return [];
        }

        $categories = $this->categoryRepository->search(new Criteria($categoryIds), $context)->getEntities();

        $breadcrumb = [];
        foreach ($categoryIds as $categoryId) {
            if ($categories->get($categoryId) === null) {
                continue;
            }

            $breadcrumb[$categoryId] = $categories->get($categoryId);
        }

        return $breadcrumb;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be set to private or deleted, without a replacement.
     */
    public function buildSeoBreadcrumb(array $twigContext, CategoryEntity $category, ?string $navigationCategoryId = null): ?array
    {
        $salesChannel = null;
        if (\array_key_exists('context', $twigContext) && $twigContext['context'] instanceof SalesChannelContext) {
            $salesChannel = $twigContext['context']->getSalesChannel();
        }

        return $this->categoryBreadcrumbBuilder->build($category, $salesChannel, $navigationCategoryId);
    }

    /**
     * @deprecated tag:v6.5.0 - Will be deleted, use getFullBreadcrumb instead.
     */
    public function getCategoryTypes(array $categoryIds, Context $context): array
    {
        return $this->buildCategoryTypes($this->getCategories($categoryIds, $context));
    }

    /**
     * @deprecated tag:v6.5.0 - Will be deleted, use getFullBreadcrumb instead.
     *
     * @param CategoryEntity[] $categories
     */
    public function buildCategoryTypes(array $categories): array
    {
        if (\count($categories) === 0) {
            return [];
        }

        $types = [];

        foreach ($categories as $category) {
            $types[$category->getId()] = $category->getType();
        }

        return $types;
    }

    private function getCategories(array $categoryIds, Context $context): array
    {
        if (\count($categoryIds) === 0) {
            return [];
        }

        return $this->categoryRepository->search(new Criteria($categoryIds), $context)->getElements();
    }
}
