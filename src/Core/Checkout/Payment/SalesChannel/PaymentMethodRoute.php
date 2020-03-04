<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class PaymentMethodRoute implements PaymentMethodRouteInterface
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

    /**
     * @OA\Get(
     *      path="/payment-method",
     *      description="Loads all available payment methods",
     *      operationId="readPaymentMethod",
     *      tags={"Store API", "Payment Method"},
     *      @OA\Parameter(
     *          parameter="limit",
     *          name="limit",
     *          in="query",
     *          description="Limit",
     *          @OA\Schema(type="integer"),
     *      ),
     *      @OA\Parameter(
     *          parameter="offset",
     *          name="offset",
     *          in="query",
     *          description="Offset",
     *          @OA\Schema(type="integer"),
     *      ),
     *      @OA\Parameter(
     *          parameter="term",
     *          name="term",
     *          in="query",
     *          description="The term to search for",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          parameter="filter",
     *          name="filter",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          parameter="aggregations",
     *          name="aggregations",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          parameter="associations",
     *          name="associations",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          parameter="onlyAvailable",
     *          name="onlyAvailable",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="int")
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="All available payment methods",
     *          @OA\JsonContent(ref="#/components/schemas/payment_method_flat")
     *     )
     * )
     * @Route("/store-api/v{version}/payment-method", name="shop-api.payment.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $paymentMethodsCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        $paymentMethodsCriteria = $this->criteriaBuilder->handleRequest(
            $request,
            $paymentMethodsCriteria,
            $this->paymentMethodDefinition,
            $context->getContext()
        );

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodsRepository->search($paymentMethodsCriteria, $context)->getEntities();
        $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        if ($request->query->getBoolean('onlyAvailable', false)) {
            $paymentMethods = $paymentMethods->filterByActiveRules($context);
        }

        return new PaymentMethodRouteResponse($paymentMethods);
    }
}
