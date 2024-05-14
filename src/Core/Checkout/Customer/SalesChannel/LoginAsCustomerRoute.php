<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Customer\LoginAsCustomerTokenGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api'], '_contextTokenRequired' => false])]
#[Package('checkout')]
class LoginAsCustomerRoute extends AbstractLoginAsCustomerRoute
{
    public const CUSTOMER_ID = 'customerId';

    public const SALES_CHANNEL_ID = 'salesChannelId';

    public const TOKEN = 'token';

    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $customerRepository,
        private readonly CartRestorer $restorer,
        private readonly LoginAsCustomerTokenGenerator $tokenGenerator
    ) {
    }

    public function getDecorated(): AbstractLoginAsCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/login/customer', name: 'store-api.account.login-as-customer', methods: ['POST'])]
    public function loginAsCustomer(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        // TODO: find better way to handle this

        if (!$data->has(self::CUSTOMER_ID)) {
            throw CustomerException::missingCustomerId();
        }

        if (!$data->has(self::TOKEN)) {
            throw CustomerException::missingToken();
        }

        $customerId = (string) $data->get(self::CUSTOMER_ID);
        $token = (string) $data->get(self::TOKEN);

        $this->tokenGenerator->validate($token, $context->getSalesChannelId(), $customerId);

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
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->get($customerId);

        if (!($customer instanceof CustomerEntity)) {
            throw CustomerException::customerNotFoundById($customerId);
        }

        return $customer;
    }
}
