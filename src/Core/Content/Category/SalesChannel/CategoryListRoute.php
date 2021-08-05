<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CategoryListRoute extends AbstractCategoryListRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

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
     * @OA\Post(
     *      path="/category",
     *      summary="Fetch a list of categories",
     *      description="Perform a filtered search for categories.",
     *      operationId="readCategoryList",
     *      tags={"Store API", "Category"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Entity search result containing categories.",
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/EntitySearchResult"),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          type="array",
     *                          property="elements",
     *                          @OA\Items(ref="#/components/schemas/Category")
     *                      )
     *                  )
     *              }
     *          )
     *     )
     * )
     * @Route("/store-api/category", name="store-api.category.search", methods={"GET", "POST"})
     */
    public function load(Criteria $criteria, SalesChannelContext $context): CategoryListRouteResponse
    {
        return new CategoryListRouteResponse($this->categoryRepository->search($criteria, $context));
    }
}
