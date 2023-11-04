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
use Shopware\Core\Checkout\Customer\Exception\CustomerOptinNotCompletedException;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[Package('customer-order')]
class AccountService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LegacyPasswordVerifier $legacyPasswordVerifier,
        private readonly AbstractSwitchDefaultAddressRoute $switchDefaultAddressRoute,
        private readonly CartRestorer $restorer
    ) {
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
     * @throws CustomerOptinNotCompletedException
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

        if ($customer->getPassword() === null
            || !password_verify($password, $customer->getPassword())) {
            throw new BadCredentialsException();
        }

        if (!$this->isCustomerConfirmed($customer)) {
            // Make sure to only throw this exception after it has been verified it was a valid login
            throw new CustomerOptinNotCompletedException($customer->getId());
        }

        return $customer;
    }

    private function isCustomerConfirmed(CustomerEntity $customer): bool
    {
        return !$customer->getDoubleOptInRegistration() || $customer->getDoubleOptInConfirmDate();
    }

    private function loginByCustomer(CustomerEntity $customer, SalesChannelContext $context): string
    {
        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'lastLogin' => new \DateTimeImmutable(),
            ],
        ], $context->getContext());

        $context = $this->restorer->restore($customer->getId(), $context);
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

        $customer = $this->fetchCustomer($criteria, $context, $includeGuest);
        if ($customer === null) {
            throw new CustomerNotFoundException($email);
        }

        return $customer;
    }

    /**
     * @throws CustomerNotFoundByIdException
     */
    private function getCustomerById(string $id, SalesChannelContext $context): CustomerEntity
    {
        $criteria = new Criteria([$id]);

        $customer = $this->fetchCustomer($criteria, $context, true);
        if ($customer === null) {
            throw new CustomerNotFoundByIdException($id);
        }

        return $customer;
    }

    /**
     * This method filters for the standard customer related constraints like active or the sales channel
     * assignment.
     * Add only filters to the $criteria for values which have an index in the database, e.g. id, or email. The rest
     * should be done via PHP because it's a lot faster to filter a few entities on PHP side with the same email
     * address, than to filter a huge numbers of rows in the DB on a not indexed column.
     */
    private function fetchCustomer(Criteria $criteria, SalesChannelContext $context, bool $includeGuest = false): ?CustomerEntity
    {
        $criteria->setTitle('account-service::fetchCustomer');

        $result = $this->customerRepository->search($criteria, $context->getContext());
        $result = $result->filter(function (CustomerEntity $customer) use ($includeGuest, $context): ?bool {
            // Skip not active users
            if (!$customer->getActive()) {
                // Customers with double opt-in will be active by default starting at Shopware 6.6.0.0,
                // remove complete if statement and always return null
                if (Feature::isActive('v6.6.0.0') || $this->isCustomerConfirmed($customer)) {
                    return null;
                }
            }

            // Skip guest if not required
            if (!$includeGuest && $customer->getGuest()) {
                return null;
            }

            // If not bound, we still need to consider it
            if ($customer->getBoundSalesChannelId() === null) {
                return true;
            }

            // It is bound, but not to the current one. Skip it
            if ($customer->getBoundSalesChannelId() !== $context->getSalesChannel()->getId()) {
                return null;
            }

            return true;
        });

        // If there is more than one account we want to return the latest, this is important
        // for guest accounts, real customer accounts should only occur once, otherwise the
        // wrong password will be validated
        if ($result->count() > 1) {
            $result->sort(fn (CustomerEntity $a, CustomerEntity $b) => ($a->getCreatedAt() <=> $b->getCreatedAt()) * -1);
        }

        return $result->first();
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
