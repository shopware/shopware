<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\Adapter\Twig\TwigContextHelper;
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

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
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

    /**
     * @phpstan-ignore shopware.deprecatedClass (not triggering deprecation to avoid polluting logs, the registered functions trigger it themselves)
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_breadcrumb_full', $this->getFullBreadcrumb(...), ['needs_context' => true]),
            new TwigFunction('sw_breadcrumb_full_by_id', $this->getFullBreadcrumbById(...), ['needs_context' => true]),
        ];
    }

    /**
     * @param array<string, mixed> $twigContext
     *
     * @return array<string, CategoryEntity|SalesChannelCategoryEntity>
     */
    public function getFullBreadcrumb(array $twigContext, CategoryEntity $category, Context|SalesChannelContext $context): array
    {
        // Methods still called in Twig behind feature flag. Deprecation is silenced to avoid polluting logs.
        $method = __METHOD__;
        Feature::silent('v6.8.0.0', static function () use ($method): void {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, $method, 'v6.8.0.0'));
        });

        if ($context instanceof Context) {
            $context = TwigContextHelper::getSalesChannelContext($twigContext) ?? $context;
        }

        $seoBreadcrumb = $this->categoryBreadcrumbBuilder->build(
            $category,
            ($context instanceof SalesChannelContext) ? $context->getSalesChannel() : null,
        );

        if ($seoBreadcrumb === null) {
            return [];
        }

        $categoryIds = array_keys($seoBreadcrumb);
        if ($categoryIds === []) {
            return [];
        }

        $criteria = new Criteria($categoryIds);
        $criteria->setTitle('breadcrumb-extension');

        if ($context instanceof SalesChannelContext) {
            $categories = $this->salesChannelCategoryRepository->search($criteria, $context)->getEntities();
        } else {
            $categories = $this->categoryRepository->search($criteria, $context)->getEntities();
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
     * @param array<string, mixed> $twigContext
     *
     * @return array<string, CategoryEntity|SalesChannelCategoryEntity>
     */
    public function getFullBreadcrumbById(array $twigContext, string $categoryId, Context|SalesChannelContext $context): array
    {
        // Methods still called in Twig behind feature flag. Deprecation is silenced to avoid polluting logs.
        $method = __METHOD__;
        Feature::silent('v6.8.0.0', static function () use ($method): void {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, $method, 'v6.8.0.0'));
        });

        if ($context instanceof Context) {
            $context = TwigContextHelper::getSalesChannelContext($twigContext) ?? $context;
        }

        if ($context instanceof SalesChannelContext) {
            $category = $this->salesChannelCategoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
        } else {
            $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
        }

        if ($category === null) {
            return [];
        }

        return $this->getFullBreadcrumb($twigContext, $category, $context);
    }
}
