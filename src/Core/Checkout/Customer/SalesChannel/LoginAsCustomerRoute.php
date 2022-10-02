<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Customer\LoginAsCustomerTokenGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(defaults={"_routeScope"={"store-api"}, "_contextTokenRequired"=false})
 */
class LoginAsCustomerRoute extends AbstractLoginAsCustomerRoute
{
    private EventDispatcherInterface $eventDispatcher;

    private EntityRepositoryInterface $customerRepository;

    private CartRestorer $restorer;

    private LoginAsCustomerTokenGenerator $tokenGenerator;

    /**
     * @internal
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $customerRepository,
        CartRestorer $restorer,
        LoginAsCustomerTokenGenerator $tokenGenerator
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->customerRepository = $customerRepository;
        $this->restorer = $restorer;
        $this->tokenGenerator = $tokenGenerator;
    }

    public function getDecorated(): AbstractLoginRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Route(path="/store-api/account/login/customer", name="store-api.account.login-as-customer", methods={"POST"})
     */
    public function loginAsCustomer(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        if (!$data->has('customerId')) {
            throw CustomerException::missingCustomerId();
        }

        if (!$data->has('salesChannelId')) {
            throw CustomerException::missingSalesChannelId();
        }

        if (!$data->has('token')) {
            throw CustomerException::missingToken();
        }

        $customerId = (string) $data->get('customerId');
        $salesChannelId = (string) $data->get('salesChannelId');
        $token = (string) $data->get('token');

        $this->tokenGenerator->validate($token, $salesChannelId, $customerId);

        $customer = $this->fetchCustomer($customerId, $context->getContext());

        $event = new CustomerBeforeLoginEvent($context, $customer->getEmail());
        $this->eventDispatcher->dispatch($event);

        $restoredCart = $this->restorer->restore($customer->getId(), $context);

        $newToken = $restoredCart->getToken();

        $event = new CustomerLoginEvent($context, $customer, $newToken);
        $this->eventDispatcher->dispatch($event);

        return new ContextTokenResponse($newToken);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws CustomerNotFoundByIdException
     */
    private function fetchCustomer(string $customerId, Context $context): CustomerEntity
    {
        /** @var CustomerEntity|null $customer */
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->get($customerId);

        if ($customer === null) {
            throw new CustomerNotFoundByIdException($customerId);
        }

        return $customer;
    }
}
