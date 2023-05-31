<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

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
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api'], '_contextTokenRequired' => false])]
class LoginAsCustomerRoute extends AbstractLoginAsCustomerRoute
{
    public const CUSTOMER_ID = 'customerId';

    public const SALES_CHANNEL_ID = 'salesChannelId';

    public const TOKEN = 'token';

    /**
     * @internal
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
        $salesChannelIdFromContext = $context->getSalesChannelId();

        // in case of existing sales channel set the salesChannelId
        // this is useful as the context is loaded from sw-access-key
        // so we do not need to set salesChannelId in the payload
        if ($salesChannelIdFromContext) {
            $data->set(self::SALES_CHANNEL_ID, $salesChannelIdFromContext);
        }

        if (!$data->has(self::CUSTOMER_ID)) {
            throw CustomerException::missingCustomerId();
        }

        if (!$data->has(self::SALES_CHANNEL_ID)) {
            throw CustomerException::missingSalesChannelId();
        }

        if (!$data->has(self::TOKEN)) {
            throw CustomerException::missingToken();
        }

        $customerId = (string) $data->get(self::CUSTOMER_ID);
        $salesChannelId = (string) $data->get(self::SALES_CHANNEL_ID);
        $token = (string) $data->get(self::TOKEN);

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
            throw CustomerException::customerNotFoundById($customerId);
        }

        return $customer;
    }
}
