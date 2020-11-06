<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ShippingMethodRoute extends AbstractShippingMethodRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $shippingMethodRepository;

    public function __construct(
        SalesChannelRepositoryInterface $shippingMethodRepository
    ) {
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    public function getDecorated(): AbstractShippingMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("shipping_method")
     * @OA\Post(
     *      path="/shipping-method",
     *      summary="Loads all available shipping methods",
     *      operationId="readShippingMethod",
     *      tags={"Store API", "Shipping Method"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="onlyAvailable", description="List only available", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(type="object",
     *              @OA\Property(
     *                  property="total",
     *                  type="integer",
     *                  description="Total amount"
     *              ),
     *              @OA\Property(
     *                  property="aggregations",
     *                  type="object",
     *                  description="aggregation result"
     *              ),
     *              @OA\Property(
     *                  property="elements",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/shipping_method_flat")
     *              )
     *          )
     *     )
     * )
     * @Route("/store-api/shipping-method", name="store-api.shipping.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        if (empty($criteria->getSorting())) {
            $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        }

        $result = $this->shippingMethodRepository->search($criteria, $context);

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $result->getEntities();
        $shippingMethods->sortShippingMethodsByPreference($context);

        if ($request->query->getBoolean('onlyAvailable', false)) {
            $shippingMethods = $shippingMethods->filterByActiveRules($context);
        }

        $result->assign(['entities' => $shippingMethods]);

        return new ShippingMethodRouteResponse($result);
    }
}
