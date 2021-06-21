<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerAlreadyConfirmedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\Exception\NoHashProvidedException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class RegisterConfirmRoute extends AbstractRegisterConfirmRoute
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
     * @var DataValidator
     */
    private $validator;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator,
        SalesChannelContextPersister $contextPersister
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
        $this->contextPersister = $contextPersister;
    }

    public function getDecorated(): AbstractRegisterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/account/register-confirm",
     *      summary="Confirm a customer registration",
     *      description="Confirms a customer registration when double opt-in is activated.

Learn more about double opt-in registration in our guide ""Register a customer"".",
     *      operationId="registerConfirm",
     *      tags={"Store API", "Login & Registration"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "hash",
     *                  "em"
     *              },
     *              @OA\Property(
     *                  property="hash",
     *                  type="string",
     *                  description="Hash from the email received"),
     *              @OA\Property(
     *                  property="em",
     *                  type="string",
     *                  description="Email hash from the email received"),
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns the logged in customer. The customer is automatically logged in with the `sw-context-token` header provided, which can be reused for subsequent requests."
     *     ),
     *      @OA\Response(
     *          response="404",
     *          description="No hash provided"
     *     ),
     *      @OA\Response(
     *          response="412",
     *          description="The customer has already been confirmed"
     *     )
     * )
     * @Route("/store-api/account/register-confirm", name="store-api.account.register.confirm", methods={"POST"})
     */
    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): CustomerResponse
    {
        if (!$dataBag->has('hash')) {
            throw new NoHashProvidedException();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $dataBag->get('hash')));
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('salutation');
        $criteria->setLimit(1);

        $customer = $this->customerRepository
            ->search($criteria, $context->getContext())
            ->first();

        if ($customer === null) {
            throw new CustomerNotFoundByHashException($dataBag->get('hash'));
        }

        $this->validator->validate(
            ['em' => $dataBag->get('em')],
            $this->getBeforeConfirmValidation(hash('sha1', $customer->getEmail()))
        );

        if ($customer->getActive()) {
            throw new CustomerAlreadyConfirmedException($customer->getId());
        }

        $this->customerRepository->update(
            [
                [
                    'id' => $customer->getId(),
                    'active' => true,
                    'doubleOptInConfirmDate' => new \DateTimeImmutable(),
                ],
            ],
            $context->getContext()
        );

        if ($customer->getGuest()) {
            $this->eventDispatcher->dispatch(new GuestCustomerRegisterEvent($context, $customer));
        } else {
            $this->eventDispatcher->dispatch(new CustomerRegisterEvent($context, $customer));
        }

        $newToken = $this->contextPersister->replace($context->getToken(), $context);

        $this->contextPersister->save(
            $newToken,
            [
                'customerId' => $customer->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $context->getSalesChannel()->getId(),
            $customer->getId()
        );

        $criteria = new Criteria([$customer->getId()]);
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('salutation');
        $criteria->setLimit(1);

        $customer = $this->customerRepository
            ->search($criteria, $context->getContext())
            ->first();

        \assert($customer instanceof CustomerEntity);

        $response = new CustomerResponse($customer);

        $event = new CustomerLoginEvent($context, $customer, $newToken);
        $this->eventDispatcher->dispatch($event);

        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

        return $response;
    }

    private function getBeforeConfirmValidation(string $emHash): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('registration.opt_in_before');
        $definition->add('em', new EqualTo(['value' => $emHash]));

        return $definition;
    }
}
