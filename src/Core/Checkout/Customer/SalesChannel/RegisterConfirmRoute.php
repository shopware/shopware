<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerAlreadyConfirmedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\Exception\NoHashProvidedException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class RegisterConfirmRoute extends AbstractRegisterConfirmRoute
{
    /**
     * @var EntityRepository
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

    private SalesChannelContextServiceInterface $contextService;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator,
        SalesChannelContextPersister $contextPersister,
        SalesChannelContextServiceInterface $contextService
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
        $this->contextPersister = $contextPersister;
        $this->contextService = $contextService;
    }

    public function getDecorated(): AbstractRegisterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
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
            [
                'em' => $dataBag->get('em'),
                'doubleOptInRegistration' => $customer->getDoubleOptInRegistration(),
            ],
            $this->getBeforeConfirmValidation(hash('sha1', $customer->getEmail()))
        );

        if ($customer->getActive() || $customer->getDoubleOptInConfirmDate() !== null) {
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

        $new = $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $context->getSalesChannel()->getId(),
                $newToken,
                $context->getLanguageId(),
                $context->getCurrencyId(),
                $context->getDomainId(),
                $context->getContext(),
                $customer->getId()
            )
        );

        $new->addState(...$context->getStates());

        if ($customer->getGuest()) {
            $this->eventDispatcher->dispatch(new GuestCustomerRegisterEvent($new, $customer));
        } else {
            $this->eventDispatcher->dispatch(new CustomerRegisterEvent($new, $customer));
        }

        $criteria = new Criteria([$customer->getId()]);
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('salutation');
        $criteria->setLimit(1);

        $customer = $this->customerRepository
            ->search($criteria, $new->getContext())
            ->first();

        \assert($customer instanceof CustomerEntity);

        $response = new CustomerResponse($customer);

        $event = new CustomerLoginEvent($new, $customer, $newToken);
        $this->eventDispatcher->dispatch($event);

        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

        return $response;
    }

    private function getBeforeConfirmValidation(string $emHash): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('registration.opt_in_before');
        $definition->add('em', new EqualTo(['value' => $emHash]));
        $definition->add('doubleOptInRegistration', new IsTrue());

        return $definition;
    }
}
