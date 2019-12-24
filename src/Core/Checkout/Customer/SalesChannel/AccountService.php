<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Composer\Semver\Constraint\ConstraintInterface;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\CustomerRecoveryHashExpiredException;
use Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatches;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

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
     * @var DataValidator
     */
    private $validator;

    /**
     * @var LegacyPasswordVerifier
     */
    private $legacyPasswordVerifier;

    /**
     * @var ValidationServiceInterface|DataValidationFactoryInterface
     */
    private $customerProfileValidationFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @param ValidationServiceInterface|DataValidationFactoryInterface $customerProfileValidationBuilder
     */
    public function __construct(
        EntityRepositoryInterface $customerAddressRepository,
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $customerRecoveryRepository,
        SalesChannelContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator,
        $customerProfileValidationBuilder,
        LegacyPasswordVerifier $legacyPasswordVerifier,
        EntityRepositoryInterface $paymentMethodRepository,
        SystemConfigService $systemConfigService,
        RouterInterface $router
    ) {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
        $this->customerRecoveryRepository = $customerRecoveryRepository;
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
        $this->legacyPasswordVerifier = $legacyPasswordVerifier;
        $this->customerProfileValidationFactory = $customerProfileValidationBuilder;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->systemConfigService = $systemConfigService;
        $this->router = $router;
    }

    /**
     * @throws CustomerNotFoundException
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

    public function saveProfile(DataBag $data, SalesChannelContext $context): void
    {
        if ($this->customerProfileValidationFactory instanceof DataValidationFactoryInterface) {
            $validation = $this->customerProfileValidationFactory->update($context);
        } else {
            $validation = $this->customerProfileValidationFactory->buildUpdateValidation($context->getContext());
        }

        $this->dispatchValidationEvent($validation, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $customer = $data->only('firstName', 'lastName', 'salutationId', 'title');

        if ($birthday = $this->getBirthday($data)) {
            $customer['birthday'] = $birthday;
        }

        $mappingEvent = new DataMappingEvent($data, $customer, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_CUSTOMER_PROFILE_SAVE);

        $customer = $mappingEvent->getOutput();
        $customer['id'] = $context->getCustomer()->getId();

        $this->customerRepository->update([$customer], $context->getContext());
    }

    public function savePassword(DataBag $data, SalesChannelContext $context): void
    {
        $this->validateCustomer($context);

        $this->validatePasswordFields($data, $context);

        $customerData = [
            'id' => $context->getCustomer()->getId(),
            'password' => $data->get('newPassword'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());
    }

    public function saveEmail(DataBag $data, SalesChannelContext $context): void
    {
        $this->validateCustomer($context);

        $this->validateEmail($data, $context);

        $customerData = [
            'id' => $context->getCustomer()->getId(),
            'email' => $data->get('email'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());
    }

    /**
     * @throws CustomerNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function generateAccountRecovery(DataBag $data, SalesChannelContext $context): void
    {
        $this->validateRecoverEmail($data, $context);

        try {
            $customer = $this->getCustomerByEmail($data->get('email'), $context);
        } catch (CustomerNotFoundException $exception) {
            throw new CustomerNotFoundException($exception->getMessage());
        }

        $customerId = $customer->getId();

        $customerIdCriteria = new Criteria();
        $customerIdCriteria->addFilter(new EqualsFilter('customerId', $customerId));
        $customerIdCriteria->addAssociation('customer');

        $repoContext = $context->getContext();

        if ($existingRecovery = $this->getCustomerRecovery($customerIdCriteria, $repoContext)) {
            $this->deleteRecoveryForCustomer($existingRecovery, $repoContext);
        }

        $recoveryData = [
            'customerId' => $customerId,
            'hash' => Random::getAlphanumericString(32),
        ];

        $this->customerRecoveryRepository->create([$recoveryData], $repoContext);

        $customerRecovery = $this->getCustomerRecovery($customerIdCriteria, $repoContext);

        if (!$customerRecovery) {
            return;
        }

        $hash = $customerRecovery->getHash();
        $recoverUrl = $this->router->generate('frontend.account.recover.password.page', ['hash' => $hash], UrlGeneratorInterface::ABSOLUTE_URL);

        $event = new CustomerAccountRecoverRequestEvent($context, $customerRecovery, $recoverUrl);
        $this->eventDispatcher->dispatch($event, CustomerAccountRecoverRequestEvent::EVENT_NAME);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws ConstraintViolationException
     * @throws CustomerNotFoundByHashException
     * @throws CustomerRecoveryHashExpiredException
     */
    public function resetPassword(DataBag $data, SalesChannelContext $context): bool
    {
        $this->validateResetPassword($data, $context);

        $hash = $data->get('hash');

        if (!$this->checkHash($hash, $context->getContext())) {
            throw new CustomerRecoveryHashExpiredException($hash);
        }

        $customerHashCriteria = new Criteria();
        $customerHashCriteria->addFilter(new EqualsFilter('hash', $hash));
        $customerHashCriteria->addAssociation('customer');

        $customerRecovery = $this->getCustomerRecovery($customerHashCriteria, $context->getContext());

        if (!$customerRecovery) {
            throw new CustomerNotFoundByHashException($hash);
        }

        $customer = $customerRecovery->getCustomer();

        if (!$customer) {
            throw new CustomerNotFoundByHashException($hash);
        }

        $customerData = [
            'id' => $customer->getId(),
            'password' => $data->get('newPassword'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());
        $this->deleteRecoveryForCustomer($customerRecovery, $context->getContext());

        return true;
    }

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
     * @throws BadCredentialsException
     * @throws UnauthorizedHttpException
     * @throws InactiveCustomerException
     */
    public function loginWithPassword(DataBag $data, SalesChannelContext $context): string
    {
        if (empty($data->get('username')) || empty($data->get('password'))) {
            throw new BadCredentialsException();
        }

        $event = new CustomerBeforeLoginEvent($context, $data->get('username'));
        $this->eventDispatcher->dispatch($event);

        try {
            $customer = $this->getCustomerByLogin(
                $data->get('username'),
                $data->get('password'),
                $context
            );
        } catch (CustomerNotFoundException | BadCredentialsException $exception) {
            throw new UnauthorizedHttpException('json', $exception->getMessage());
        }

        if (!$customer->getActive()) {
            throw new InactiveCustomerException($customer->getId());
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

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'lastLogin' => new \DateTimeImmutable(),
            ],
        ], $context->getContext());

        $event = new CustomerLoginEvent($context, $customer, $newToken);
        $this->eventDispatcher->dispatch($event);

        return $newToken;
    }

    public function logout(SalesChannelContext $context): void
    {
        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );

        $event = new CustomerLogoutEvent($context, $context->getCustomer());
        $this->eventDispatcher->dispatch($event);
    }

    public function setNewsletterFlag(CustomerEntity $customer, bool $newsletter, SalesChannelContext $context): void
    {
        $customer->setNewsletter($newsletter);

        $this->customerRepository->update([[
            'id' => $customer->getId(),
            'newsletter' => $newsletter,
        ]], $context->getContext());
    }

    /**
     * @throws InvalidUuidException
     * @throws UnknownPaymentMethodException
     */
    public function changeDefaultPaymentMethod(string $paymentMethodId, RequestDataBag $requestDataBag, CustomerEntity $customer, SalesChannelContext $context): void
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

    public function getCustomerRecovery(Criteria $criteria, Context $context): ?CustomerRecoveryEntity
    {
        return $this->customerRecoveryRepository->search($criteria, $context)->first();
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateResetPassword(DataBag $data, SalesChannelContext $context): void
    {
        $definition = new DataValidationDefinition('customer.password.update');

        $minPasswordLength = $this->systemConfigService->get('core.loginRegistration.passwordMinLength', $context->getSalesChannel()->getId());

        $definition->add('newPassword', new NotBlank(), new Length(['min' => $minPasswordLength]), new EqualTo(['propertyPath' => 'newPasswordConfirm']));

        $this->dispatchValidationEvent($definition, $context->getContext());

        $this->validator->validate($data->all(), $definition);

        $this->tryValidateEqualtoConstraint($data->all(), 'newPassword', $definition);
    }

    private function deleteRecoveryForCustomer(CustomerRecoveryEntity $existingRecovery, Context $context): void
    {
        $recoveryData = [
            'id' => $existingRecovery->getId(),
        ];

        $this->customerRecoveryRepository->delete([$recoveryData], $context);
    }

    private function validateEmail(DataBag $data, SalesChannelContext $context): void
    {
        $validation = new DataValidationDefinition('customer.email.update');

        $validation
            ->add(
                'email',
                new Email(),
                new EqualTo(['propertyPath' => 'emailConfirmation']),
                new CustomerEmailUnique(['context' => $context->getContext()])
            )
            ->add('password', new CustomerPasswordMatches(['context' => $context]));

        $this->dispatchValidationEvent($validation, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $this->tryValidateEqualtoConstraint($data->all(), 'email', $validation);
    }

    private function validateRecoverEmail(DataBag $data, SalesChannelContext $context): void
    {
        $validation = new DataValidationDefinition('customer.email.recover');

        $validation
            ->add(
                'email',
                new Email()
            );

        $this->dispatchValidationEvent($validation, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $this->tryValidateEqualtoConstraint($data->all(), 'email', $validation);
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

    private function getBirthday(DataBag $data): ?\DateTimeInterface
    {
        $birthdayDay = $data->get('birthdayDay');
        $birthdayMonth = $data->get('birthdayMonth');
        $birthdayYear = $data->get('birthdayYear');

        if (!$birthdayDay || !$birthdayMonth || !$birthdayYear) {
            return null;
        }

        return new \DateTime(sprintf(
            '%s-%s-%s',
            $birthdayYear,
            $birthdayMonth,
            $birthdayDay
        ));
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
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

    /**
     * @throws ConstraintViolationException
     */
    private function tryValidateEqualtoConstraint(array $data, string $field, DataValidationDefinition $validation): void
    {
        $validations = $validation->getProperties();

        if (!array_key_exists($field, $validations)) {
            return;
        }

        /** @var array $fieldValidations */
        $fieldValidations = $validations[$field];

        /** @var EqualTo|null $equalityValidation */
        $equalityValidation = null;

        /** @var ConstraintInterface $emailValidation */
        foreach ($fieldValidations as $emailValidation) {
            if ($emailValidation instanceof EqualTo) {
                $equalityValidation = $emailValidation;

                break;
            }
        }

        if (!$equalityValidation instanceof EqualTo) {
            return;
        }

        $compareValue = $data[$equalityValidation->propertyPath] ?? null;
        if ($data[$field] === $compareValue) {
            return;
        }

        $message = str_replace('{{ compared_value }}', $compareValue, $equalityValidation->message);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $equalityValidation->message, [], '', $field, $data[$field]));

        throw new ConstraintViolationException($violations, $data);
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validatePasswordFields(DataBag $data, SalesChannelContext $context): void
    {
        $definition = new DataValidationDefinition('customer.password.update');

        $minPasswordLength = $this->systemConfigService->get('core.loginRegistration.passwordMinLength', $context->getSalesChannel()->getId());

        $definition
            ->add('newPassword', new NotBlank(), new Length(['min' => $minPasswordLength]), new EqualTo(['propertyPath' => 'newPasswordConfirm']))
            ->add('password', new CustomerPasswordMatches(['context' => $context]));

        $this->dispatchValidationEvent($definition, $context->getContext());

        $this->validator->validate($data->all(), $definition);

        $this->tryValidateEqualtoConstraint($data->all(), 'newPassword', $definition);
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
