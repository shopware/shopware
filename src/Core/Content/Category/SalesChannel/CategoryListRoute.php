<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    #[Route(path: '/store-api/category', name: 'store-api.category.search', methods: ['GET', 'POST'], defaults: ['_entity' => 'category'])]
    public function load(Criteria $criteria, SalesChannelContext $context): CategoryListRouteResponse
    {
        return new CategoryListRouteResponse($this->categoryRepository->search($criteria, $context));
    }
}
