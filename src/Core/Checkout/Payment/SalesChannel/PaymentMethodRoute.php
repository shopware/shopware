<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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

    public function __construct(
        SalesChannelRepositoryInterface $paymentMethodsRepository
    ) {
        $this->paymentMethodsRepository = $paymentMethodsRepository;
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Entity("payment_method")
     * @OA\Get(
     *      path="/payment-method",
     *      description="Loads all available payment methods",
     *      operationId="readPaymentMethod",
     *      tags={"Store API", "Payment Method"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          parameter="onlyAvailable",
     *          name="onlyAvailable",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="int")
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
     * @Route("/store-api/v{version}/payment-method", name="store-api.payment.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        $result = $this->paymentMethodsRepository->search($criteria, $context);

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $result->getEntities();
        $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        if ($request->query->getBoolean('onlyAvailable', false)) {
            $paymentMethods = $paymentMethods->filterByActiveRules($context);
        }

        $result->assign(['entities' => $paymentMethods]);

        return new PaymentMethodRouteResponse($result);
    }
}
