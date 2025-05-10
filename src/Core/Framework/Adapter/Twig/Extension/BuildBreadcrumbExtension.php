<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('framework')]
class BuildBreadcrumbExtension extends AbstractExtension
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<EntityCollection<SalesChannelCategoryEntity>> $categoryRepository
     */
    public function __construct(
        private readonly CategoryBreadcrumbBuilder $categoryBreadcrumbBuilder,
        private readonly SalesChannelRepository $categoryRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_breadcrumb_full', $this->getFullBreadcrumb(...)),
            new TwigFunction('sw_breadcrumb_full_by_id', $this->getFullBreadcrumbById(...)),
        ];
    }

    /**
     * @return array<string, SalesChannelCategoryEntity>
     */
    public function getFullBreadcrumb(CategoryEntity $category, SalesChannelContext $context): array
    {
        $seoBreadcrumb = $this->categoryBreadcrumbBuilder->build($category, $context->getSalesChannel());
        if ($seoBreadcrumb === null) {
            return [];
        }

        $categoryIds = array_keys($seoBreadcrumb);
        if (empty($categoryIds)) {
            return [];
        }

        $criteria = new Criteria($categoryIds);
        $criteria->setTitle('breadcrumb-extension');
        $categories = $this->categoryRepository->search($criteria, $context)->getEntities();

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
     * @return array<string, SalesChannelCategoryEntity>
     */
    public function getFullBreadcrumbById(string $categoryId, SalesChannelContext $context): array
    {
        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
        if ($category === null) {
            return [];
        }

        return $this->getFullBreadcrumb($category, $context);
    }
}
