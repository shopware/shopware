<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
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
class PaymentMethodRoute extends AbstractPaymentMethodRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $paymentMethodsRepository;

    public function __construct(SalesChannelRepositoryInterface $paymentMethodsRepository)
    {
        $this->paymentMethodsRepository = $paymentMethodsRepository;
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("payment_method")
     * @OA\Post (
     *      path="/payment-method",
     *      summary="Loads all available payment methods",
     *      operationId="readPaymentMethod",
     *      tags={"Store API", "Payment Method"},
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
     *                  @OA\Items(ref="#/components/schemas/payment_method_flat")
     *              )
     *       )
     *    )
     * )
     * @Route("/store-api/payment-method", name="store-api.payment.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position'))
            ->addAssociation('media');

        $result = $this->paymentMethodsRepository->search($criteria, $context);

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $result->getEntities();
        $paymentMethods->sortPaymentMethodsByPreference($context);

        if ($request->query->getBoolean('onlyAvailable', false)) {
            $paymentMethods = $paymentMethods->filterByActiveRules($context);
        }

        $result->assign(['entities' => $paymentMethods]);

        return new PaymentMethodRouteResponse($result);
    }
}
