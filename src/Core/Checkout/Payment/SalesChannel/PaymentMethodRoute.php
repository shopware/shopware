<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
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

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var SalesChannelPaymentMethodDefinition
     */
    private $paymentMethodDefinition;

    public function __construct(
        SalesChannelRepositoryInterface $paymentMethodsRepository,
        RequestCriteriaBuilder $criteriaBuilder,
        SalesChannelPaymentMethodDefinition $paymentMethodDefinition
    ) {
        $this->paymentMethodsRepository = $paymentMethodsRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->paymentMethodDefinition = $paymentMethodDefinition;
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
     *          description="All available payment methods",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/payment_method_flat"))
     *     )
     * )
     * @Route("/store-api/v{version}/payment-method", name="store-api.payment.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null): PaymentMethodRouteResponse
    {
        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $this->paymentMethodDefinition, $context->getContext());
        }
        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodsRepository->search($criteria, $context)->getEntities();
        $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        if ($request->query->getBoolean('onlyAvailable', false)) {
            $paymentMethods = $paymentMethods->filterByActiveRules($context);
        }

        return new PaymentMethodRouteResponse($paymentMethods);
    }
}
