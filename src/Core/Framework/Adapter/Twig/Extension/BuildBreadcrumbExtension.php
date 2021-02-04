<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Category\CategoryCollection;
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
            new TwigFunction('sw_breadcrumb', [$this, 'buildSeoBreadcrumb'], ['needs_context' => true]),
            new TwigFunction('sw_breadcrumb_types', [$this, 'getCategoryTypes']),
        ];
    }

    public function buildSeoBreadcrumb(array $twigContext, CategoryEntity $category, ?string $navigationCategoryId = null): ?array
    {
        $salesChannel = null;
        if (\array_key_exists('context', $twigContext) && $twigContext['context'] instanceof SalesChannelContext) {
            $salesChannel = $twigContext['context']->getSalesChannel();
        }

        return $this->categoryBreadcrumbBuilder->build($category, $salesChannel, $navigationCategoryId);
    }

    public function getCategoryTypes(array $categoryIds, Context $context): array
    {
        if (\count($categoryIds) === 0) {
            return [];
        }

        $types = [];

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search(new Criteria($categoryIds), $context)->getEntities();

        foreach ($categories as $category) {
            $types[$category->getId()] = $category->getType();
        }

        return $types;
    }
}
