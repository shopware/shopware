<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\FindVariant;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Exception\VariantNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class FindProductVariantRoute extends AbstractFindProductVariantRoute
{
    private SalesChannelRepository $productRepository;

    /**
     * @internal
     */
    public function __construct(
        SalesChannelRepository $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    public function getDecorated(): AbstractFindProductVariantRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.4.14.0")
     * @Entity("product")
     * @OA\Post(
     *      path="/product/{productId}/find-variant",
     *      summary="Search for a matching variant by product options.",
     *      description="Performs a search for product variants and returns the best matching variant.",
     *      operationId="searchProductVariantIds",
     *      tags={"Store API","Product"},
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              required={
     *                  "options"
     *              },
     *              @OA\Property(
     *                  property="options",
     *                  description="The options parameter for the variant to find.",
     *                  type="array",
     *                  @OA\Items(type="string"),
     *              ),
     *              @OA\Property(
     *                  property="switchedGroup",
     *                  description="The id of the option group that has been switched.",
     *                  type="string",
     *              ),
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="productId",
     *          description="Product ID",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns an FoundCombination struct containing the ids matching the search.",
     *          @OA\JsonContent(ref="#/components/schemas/FindProductVariantRouteResponse")
     *     )
     * )
     * @Route("/store-api/product/{productId}/find-variant", name="store-api.product.find-variant", methods={"POST"})
     */
    public function load(string $productId, Request $request, SalesChannelContext $context): FindProductVariantRouteResponse
    {
        /** @var string|null $switchedGroup */
        $switchedGroup = $request->get('switchedGroup');

        /** @var array $options */
        $options = $request->get('options') ? $request->get('options', []) : [];

        $variantId = $this->searchForOptions($productId, $context, $options);

        if ($variantId !== null) {
            return new FindProductVariantRouteResponse(new FoundCombination($variantId, $options));
        }

        while (\count($options) > 1) {
            foreach ($options as $groupId => $_optionId) {
                if ($groupId !== $switchedGroup) {
                    unset($options[$groupId]);

                    break;
                }
            }

            $variantId = $this->searchForOptions($productId, $context, $options);

            if ($variantId) {
                return new FindProductVariantRouteResponse(new FoundCombination($variantId, $options));
            }
        }

        throw new VariantNotFoundException($productId, $options);
    }

    private function searchForOptions(
        string $productId,
        SalesChannelContext $salesChannelContext,
        array $options
    ): ?string {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $productId))
            ->setLimit(1);

        foreach ($options as $optionId) {
            $criteria->addFilter(new EqualsFilter('product.optionIds', $optionId));
        }

        return $this->productRepository->searchIds($criteria, $salesChannelContext)->firstId();
    }
}
