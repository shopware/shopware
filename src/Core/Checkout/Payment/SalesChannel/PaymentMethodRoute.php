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
    private SalesChannelRepositoryInterface $paymentMethodsRepository;

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
     *      summary="Fetch payment methods",
     *      description="Perform a filtered search for payment methods, for use in the checkout process.",
     *      operationId="readPaymentMethod",
     *      tags={"Store API", "Payment & Shipping"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="onlyAvailable",
     *          description="List only available payment methods. This filters payment methods which can not be used in the actual context because of their availability rule.",
     *          @OA\Schema(type="boolean"),
     *          in="query"
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Entity search result containing payment methods",
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/EntitySearchResult"),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          type="array",
     *                          property="elements",
     *                          @OA\Items(ref="#/components/schemas/PaymentMethod")
     *                      )
     *                  )
     *              }
     *          )
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

        if ($request->query->getBoolean('onlyAvailable', false)) {
            $paymentMethods = $paymentMethods->filterByActiveRules($context);
        }

        $result->assign(['entities' => $paymentMethods, 'elements' => $paymentMethods, 'total' => $paymentMethods->count()]);

        return new PaymentMethodRouteResponse($result);
    }
}
