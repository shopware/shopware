<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class CategoryListRoute extends AbstractCategoryListRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(private readonly SalesChannelRepository $categoryRepository)
    {
    }

    public function getDecorated(): AbstractCategoryListRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/category', name: 'store-api.category.search', defaults: ['_entity' => 'category'], methods: ['GET', 'POST'])]
    public function load(Criteria $criteria, SalesChannelContext $context): CategoryListRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('active', true));

        $rootIds = array_filter([
            $context->getSalesChannel()->getNavigationCategoryId(),
            $context->getSalesChannel()->getFooterCategoryId(),
            $context->getSalesChannel()->getServiceCategoryId(),
        ]);

        if (!empty($rootIds)) {
            $filter = new OrFilter();

            foreach ($rootIds as $rootId) {
                $filter->addQuery(new EqualsFilter('id', $rootId));
                $filter->addQuery(new ContainsFilter('path', '|' . $rootId . '|'));
            }

            $criteria->addFilter($filter);
        }

        return new CategoryListRouteResponse($this->categoryRepository->search($criteria, $context));
    }
}
