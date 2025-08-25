<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
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
     * @param SalesChannelRepository<EntityCollection<SalesChannelCategoryEntity>> $salesChannelCategoryRepository
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly CategoryBreadcrumbBuilder $categoryBreadcrumbBuilder,
        private readonly SalesChannelRepository $salesChannelCategoryRepository,
        private readonly EntityRepository $categoryRepository,
    ) {
    }

    public function getFunctions(): array
    {
        /** @deprecated tag:v6.8.0 - Remove `needs_context` option, as the SalesChannelContext is required and the Twig Context is not needed anymore */
        return [
            new TwigFunction('sw_breadcrumb_full', $this->getFullBreadcrumb(...), ['needs_context' => true]),
            new TwigFunction('sw_breadcrumb_full_by_id', $this->getFullBreadcrumbById(...), ['needs_context' => true]),
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - Parameter $twigContext will be removed, as it is not needed anymore and the type of `$context` will be changed to `SalesChannelContext`
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will only return `array<string, SalesChannelCategoryEntity>`
     *
     * @param array<string, mixed> $twigContext
     *
     * @return array<string, CategoryEntity|SalesChannelCategoryEntity>
     */
    public function getFullBreadcrumb(array $twigContext, CategoryEntity $category, Context|SalesChannelContext $context): array
    {
        if (Feature::isActive('v6.8.0.0')) {
            \assert($context instanceof SalesChannelContext);

            $seoBreadcrumb = $this->categoryBreadcrumbBuilder->build($category, $context->getSalesChannel());
        } else {
            if ($context instanceof Context) {
                Feature::triggerDeprecationOrThrow(
                    'v6.8.0.0',
                    'Passing the Context to getFullBreadcrumb is deprecated. The SalesChannelContext will be required in v6.8.0.0.'
                );

                $context = $this->getSalesChannelContext($twigContext) ?? $context;
            }

            $seoBreadcrumb = $this->categoryBreadcrumbBuilder->build(
                $category,
                ($context instanceof SalesChannelContext) ? $context->getSalesChannel() : null,
            );
        }

        if ($seoBreadcrumb === null) {
            return [];
        }

        $categoryIds = array_keys($seoBreadcrumb);
        if (empty($categoryIds)) {
            return [];
        }

        $criteria = new Criteria($categoryIds);
        $criteria->setTitle('breadcrumb-extension');

        if (Feature::isActive('v6.8.0.0')) {
            \assert($context instanceof SalesChannelContext);

            $categories = $this->salesChannelCategoryRepository->search($criteria, $context)->getEntities();
        } else {
            if ($context instanceof SalesChannelContext) {
                $categories = $this->salesChannelCategoryRepository->search($criteria, $context)->getEntities();
            } else {
                $categories = $this->categoryRepository->search($criteria, $context)->getEntities();
            }
        }

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
     * @deprecated tag:v6.8.0 - Parameter $twigContext will be removed, as it is not needed anymore and the type of `$context` will be changed to `SalesChannelContext`
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will only return `array<string, SalesChannelCategoryEntity>`
     *
     * @param array<string, mixed> $twigContext
     *
     * @return array<string, CategoryEntity|SalesChannelCategoryEntity>
     */
    public function getFullBreadcrumbById(array $twigContext, string $categoryId, Context|SalesChannelContext $context): array
    {
        if (Feature::isActive('v6.8.0.0')) {
            \assert($context instanceof SalesChannelContext);

            $category = $this->salesChannelCategoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
        } else {
            if ($context instanceof Context) {
                Feature::triggerDeprecationOrThrow(
                    'v6.8.0.0',
                    'Passing the Context to getFullBreadcrumbById is deprecated. The SalesChannelContext will be required in v6.8.0.0.'
                );

                $context = $this->getSalesChannelContext($twigContext) ?? $context;
            }

            if ($context instanceof SalesChannelContext) {
                $category = $this->salesChannelCategoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
            } else {
                $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
            }
        }

        if ($category === null) {
            return [];
        }

        return $this->getFullBreadcrumb($twigContext, $category, $context);
    }

    /**
     * @param array<string, mixed> $twigContext
     */
    private function getSalesChannelContext(array $twigContext): ?SalesChannelContext
    {
        $context = $twigContext['context'] ?? null;
        if ($context instanceof SalesChannelContext) {
            return $context;
        }

        return null;
    }
}
