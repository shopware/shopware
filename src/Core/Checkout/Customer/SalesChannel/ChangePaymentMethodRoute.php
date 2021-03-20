<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 * @ContextTokenRequired()
 */
class ChangePaymentMethodRoute extends AbstractChangePaymentMethodRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    public function __construct(EntityRepositoryInterface $customerRepository, EventDispatcherInterface $eventDispatcher, EntityRepositoryInterface $paymentMethodRepository)
    {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function getDecorated(): AbstractChangePaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/account/change-payment-method/{paymentMethodId}",
     *      summary="Change the customer's default payment method",
     *      description="Changes a customer's default (preselected) payment method.",
     *      operationId="changePaymentMethod",
     *      tags={"Store API", "Profile"},
     *      @OA\Parameter(
     *        name="paymentMethodId",
     *        in="path",
     *        description="Identifier of the desired default payment method",
     *        @OA\Schema(type="string"),
     *        required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns a success response indicating a successful update.",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     )
     * )
     * @LoginRequired()
     * @Route(path="/store-api/account/change-payment-method/{paymentMethodId}", name="store-api.account.set.payment-method", methods={"POST"})
     */
    public function change(string $paymentMethodId, RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        $this->validatePaymentMethodId($paymentMethodId, $context->getContext());

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'defaultPaymentMethodId' => $paymentMethodId,
            ],
        ], $context->getContext());

        $event = new CustomerChangedPaymentMethodEvent($context, $customer, $requestDataBag);
        $this->eventDispatcher->dispatch($event);

        return new SuccessResponse();
    }

    /**
     * @throws InvalidUuidException
     * @throws UnknownPaymentMethodException
     */
    private function validatePaymentMethodId(string $paymentMethodId, Context $context): void
    {
        if (!Uuid::isValid($paymentMethodId)) {
            throw new InvalidUuidException($paymentMethodId);
        }

        /** @var PaymentMethodEntity|null $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->search(new Criteria([$paymentMethodId]), $context)->get($paymentMethodId);

        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }
    }
}
