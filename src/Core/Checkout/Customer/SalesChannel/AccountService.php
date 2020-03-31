<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AccountService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRecoveryRepository;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var LegacyPasswordVerifier
     */
    private $legacyPasswordVerifier;

    /**
     * @var AbstractLogoutRoute
     */
    private $logoutRoute;

    /**
     * @var AbstractLoginRoute
     */
    private $loginRoute;

    /**
     * @var AbstractChangePasswordRoute
     */
    private $changePasswordRoute;

    /**
     * @var AbstractChangePaymentMethodRoute
     */
    private $changePaymentMethodRoute;

    /**
     * @var AbstractChangeEmailRoute
     */
    private $changeEmailRoute;

    /**
     * @var AbstractChangeCustomerProfileRoute
     */
    private $changeCustomerProfileRoute;

    /**
     * @var AbstractResetPasswordRoute
     */
    private $resetPasswordRoute;

    /**
     * @var AbstractSendPasswordRecoveryMailRoute
     */
    private $sendPasswordRecoveryMailRoute;

    public function __construct(
        EntityRepositoryInterface $customerAddressRepository,
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $customerRecoveryRepository,
        SalesChannelContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher,
        LegacyPasswordVerifier $legacyPasswordVerifier,
        AbstractLogoutRoute $logoutRoute,
        AbstractLoginRoute $loginRoute,
        AbstractChangePasswordRoute $changePasswordRoute,
        AbstractChangePaymentMethodRoute $changePaymentMethodRoute,
        AbstractChangeEmailRoute $changeEmailRoute,
        AbstractChangeCustomerProfileRoute $changeCustomerProfileRoute,
        AbstractResetPasswordRoute $resetPasswordRoute,
        AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute
    ) {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
        $this->customerRecoveryRepository = $customerRecoveryRepository;
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->legacyPasswordVerifier = $legacyPasswordVerifier;
        $this->logoutRoute = $logoutRoute;
        $this->loginRoute = $loginRoute;
        $this->changePasswordRoute = $changePasswordRoute;
        $this->changePaymentMethodRoute = $changePaymentMethodRoute;
        $this->changeEmailRoute = $changeEmailRoute;
        $this->changeCustomerProfileRoute = $changeCustomerProfileRoute;
        $this->resetPasswordRoute = $resetPasswordRoute;
        $this->sendPasswordRecoveryMailRoute = $sendPasswordRecoveryMailRoute;
    }

    /**
     * @throws CustomerNotFoundException
     *
     * @deprecated tag:v6.3.0 use customer repository instead
     */
    public function getCustomerByEmail(string $email, SalesChannelContext $context, bool $includeGuest = false): CustomerEntity
    {
        $customers = $this->getCustomersByEmail($email, $context, $includeGuest);

        $customerCount = $customers->count();
        if ($customerCount === 1) {
            return $customers->first();
        }

        if ($includeGuest && $customerCount) {
            $customers->sort(static function (CustomerEntity $a, CustomerEntity $b) {
                return $a->getCreatedAt() <=> $b->getCreatedAt();
            });

            return $customers->last();
        }

        throw new CustomerNotFoundException($email);
    }

    /**
     * @deprecated tag:v6.3.0 use customer repository instead
     */
    public function getCustomersByEmail(string $email, SalesChannelContext $context, bool $includeGuests = true): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer.email', $email));
        if (!$includeGuests) {
            $criteria->addFilter(new EqualsFilter('customer.guest', 0));
        }
        // TODO NEXT-389 we have to check an option like "bind customer to salesChannel"
        // todo in this case we have to filter "customer.salesChannelId is null or salesChannelId = :current"

        return $this->customerRepository->search($criteria, $context->getContext());
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute instead
     */
    public function saveProfile(DataBag $data, SalesChannelContext $context): void
    {
        $this->changeCustomerProfileRoute->change($data->toRequestDataBag(), $context);
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangePasswordRoute instead
     */
    public function savePassword(DataBag $data, SalesChannelContext $context): void
    {
        $this->changePasswordRoute->change($data->toRequestDataBag(), $context);
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute instead
     */
    public function saveEmail(DataBag $data, SalesChannelContext $context): void
    {
        $this->changeEmailRoute->change($data->toRequestDataBag(), $context);
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Checkout\Customer\SalesChannel\AbstractSendPasswordRecoveryMailRoute instead
     */
    public function generateAccountRecovery(DataBag $data, SalesChannelContext $context): void
    {
        if (!$data->has('storefrontUrl')) {
            $data->set('storefrontUrl', $context->getSalesChannel()->getDomains()->first()->getUrl());
        }

        $this->sendPasswordRecoveryMailRoute->sendRecoveryMail($data->toRequestDataBag(), $context, false);
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute instead
     */
    public function resetPassword(DataBag $data, SalesChannelContext $context): bool
    {
        $this->resetPasswordRoute->resetPassword($data->toRequestDataBag(), $context);

        return true;
    }

    /**
     * @deprecated tag:v6.3.0 use customer_recovery repository instead
     */
    public function checkHash(string $hash, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('hash', $hash)
        );

        $recovery = $this->getCustomerRecovery($criteria, $context);

        $validDateTime = (new \DateTime())->sub(new \DateInterval('PT2H'));

        return $recovery && $validDateTime < $recovery->getCreatedAt();
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultBillingAddress(string $addressId, SalesChannelContext $context): void
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);

        $data = [
            'id' => $context->getCustomer()->getId(),
            'defaultBillingAddressId' => $addressId,
        ];
        $this->customerRepository->update([$data], $context->getContext());
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultShippingAddress(string $addressId, SalesChannelContext $context): void
    {
        $this->validateCustomer($context);
        $this->validateAddressId($addressId, $context);

        $data = [
            'id' => $context->getCustomer()->getId(),
            'defaultShippingAddressId' => $addressId,
        ];
        $this->customerRepository->update([$data], $context->getContext());
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
        } catch (CustomerNotFoundException | BadCredentialsException $exception) {
            throw new UnauthorizedHttpException('json', $exception->getMessage());
        }

        $newToken = $this->contextPersister->replace($context->getToken());
        $this->contextPersister->save(
            $newToken,
            [
                'customerId' => $customer->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );

        $event = new CustomerLoginEvent($context, $customer, $newToken);
        $this->eventDispatcher->dispatch($event);

        return $newToken;
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute instead
     */
    public function loginWithPassword(DataBag $data, SalesChannelContext $context): string
    {
        return $this->loginRoute->login($data->toRequestDataBag(), $context)->getToken();
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute instead
     */
    public function logout(SalesChannelContext $context): void
    {
        $this->logoutRoute->logout($context);
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface instead
     */
    public function setNewsletterFlag(CustomerEntity $customer, bool $newsletter, SalesChannelContext $context): void
    {
        $customer->setNewsletter($newsletter);

        $this->customerRepository->update([[
            'id' => $customer->getId(),
            'newsletter' => $newsletter,
        ]], $context->getContext());
    }

    /**
     * @deprecated tag:v6.3.0 use \Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangePaymentMethodRoute instead
     */
    public function changeDefaultPaymentMethod(string $paymentMethodId, RequestDataBag $requestDataBag, CustomerEntity $customer, SalesChannelContext $context): void
    {
        $this->changePaymentMethodRoute->change($paymentMethodId, $requestDataBag, $context);
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
     * @deprecated tag:v6.3.0 use customer_recovery repository instead
     */
    public function getCustomerRecovery(Criteria $criteria, Context $context): ?CustomerRecoveryEntity
    {
        return $this->customerRecoveryRepository->search($criteria, $context)->first();
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    private function validateCustomer(SalesChannelContext $context): void
    {
        if ($context->getCustomer()) {
            return;
        }

        throw new CustomerNotLoggedInException();
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    private function validateAddressId(string $addressId, SalesChannelContext $context): void
    {
        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('customerId', $context->getCustomer()->getId()));

        $searchResult = $this->customerAddressRepository->searchIds($criteria, $context->getContext());
        if ($searchResult->getTotal()) {
            return;
        }

        throw new AddressNotFoundException($addressId);
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
