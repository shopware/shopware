<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AccountService
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
     * @var LegacyPasswordVerifier
     */
    private $legacyPasswordVerifier;

    /**
     * @var AbstractSwitchDefaultAddressRoute
     */
    private $switchDefaultAddressRoute;

    /**
     * @var SalesChannelContextRestorer
     */
    private $contextRestorer;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        LegacyPasswordVerifier $legacyPasswordVerifier,
        AbstractSwitchDefaultAddressRoute $switchDefaultAddressRoute,
        SalesChannelContextRestorer $contextRestorer
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->legacyPasswordVerifier = $legacyPasswordVerifier;
        $this->switchDefaultAddressRoute = $switchDefaultAddressRoute;
        $this->contextRestorer = $contextRestorer;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultBillingAddress(string $addressId, SalesChannelContext $context, CustomerEntity $customer): void
    {
        $this->switchDefaultAddressRoute->swap($addressId, AbstractSwitchDefaultAddressRoute::TYPE_BILLING, $context, $customer);
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultShippingAddress(string $addressId, SalesChannelContext $context, CustomerEntity $customer): void
    {
        $this->switchDefaultAddressRoute->swap($addressId, AbstractSwitchDefaultAddressRoute::TYPE_SHIPPING, $context, $customer);
    }

    /**
     * @throws BadCredentialsException
     * @throws UnauthorizedHttpException
     */
    public function login(string $email, SalesChannelContext $context, bool $includeGuest = false): string
    {
        if (empty($email)) {
            throw new BadCredentialsException();
        }

        $event = new CustomerBeforeLoginEvent($context, $email);
        $this->eventDispatcher->dispatch($event);

        try {
            $customer = $this->getCustomerByEmail($email, $context, $includeGuest);
        } catch (CustomerNotFoundException $exception) {
            throw new UnauthorizedHttpException('json', $exception->getMessage());
        }

        return $this->loginByCustomer($customer, $context);
    }

    /**
     * @throws BadCredentialsException
     * @throws UnauthorizedHttpException
     */
    public function loginById(string $id, SalesChannelContext $context): string
    {
        if (!Uuid::isValid($id)) {
            throw new BadCredentialsException();
        }

        try {
            $customer = $this->getCustomerById($id, $context);
        } catch (CustomerNotFoundByIdException $exception) {
            throw new UnauthorizedHttpException('json', $exception->getMessage());
        }

        $event = new CustomerBeforeLoginEvent($context, $customer->getEmail());
        $this->eventDispatcher->dispatch($event);

        return $this->loginByCustomer($customer, $context);
    }

    /**
     * @throws CustomerNotFoundException
     * @throws BadCredentialsException
     * @throws InactiveCustomerException
     */
    public function getCustomerByLogin(string $email, string $password, SalesChannelContext $context): CustomerEntity
    {
        $customer = $this->getCustomerByEmail($email, $context);

        if ($customer->hasLegacyPassword()) {
            if (!$this->legacyPasswordVerifier->verify($password, $customer)) {
                throw new BadCredentialsException();
            }

            $this->updatePasswordHash($password, $customer, $context->getContext());

            return $customer;
        }

        if (!password_verify($password, $customer->getPassword())) {
            throw new BadCredentialsException();
        }

        return $customer;
    }

    /**
     * @throws InactiveCustomerException
     */
    private function loginByCustomer(CustomerEntity $customer, SalesChannelContext $context): string
    {
        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'lastLogin' => new \DateTimeImmutable(),
            ],
        ], $context->getContext());

        $context = $this->contextRestorer->restore($customer->getId(), $context);
        $newToken = $context->getToken();

        $event = new CustomerLoginEvent($context, $customer, $newToken);
        $this->eventDispatcher->dispatch($event);

        return $newToken;
    }

    /**
     * @throws CustomerNotFoundException
     */
    private function getCustomerByEmail(string $email, SalesChannelContext $context, bool $includeGuest = false): CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        if (!$includeGuest) {
            $criteria->addFilter(new EqualsFilter('guest', false));
        }
        $criteria->addSorting(new FieldSorting('createdAt'));
        $criteria->setLimit(1);

        $customer = $this->fetchCustomer($criteria, $context);

        if ($customer === null) {
            throw new CustomerNotFoundException($email);
        }

        return $customer;
    }

    private function getCustomerById(string $id, SalesChannelContext $context): CustomerEntity
    {
        $criteria = new Criteria([$id]);
        $customer = $this->fetchCustomer($criteria, $context);

        if ($customer === null) {
            throw new CustomerNotFoundByIdException($id);
        }

        return $customer;
    }

    private function fetchCustomer(Criteria $criteria, SalesChannelContext $context): ?CustomerEntity
    {
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('boundSalesChannelId', null),
            new EqualsFilter('boundSalesChannelId', $context->getSalesChannel()->getId()),
        ]));

        return $this->customerRepository->search($criteria, $context->getContext())->first();
    }

    private function updatePasswordHash(string $password, CustomerEntity $customer, Context $context): void
    {
        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'password' => $password,
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ],
        ], $context);
    }
}
