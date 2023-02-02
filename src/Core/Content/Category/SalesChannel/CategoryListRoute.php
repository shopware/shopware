<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CategoryListRoute extends AbstractCategoryListRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @internal
     */
    public function __construct(SalesChannelRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function getDecorated(): AbstractCategoryListRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     * @Entity("category")
     * @Route("/store-api/category", name="store-api.category.search", methods={"GET", "POST"})
     */
    public function load(Criteria $criteria, SalesChannelContext $context): CategoryListRouteResponse
    {
        return new CategoryListRouteResponse($this->categoryRepository->search($criteria, $context));
    }
}
