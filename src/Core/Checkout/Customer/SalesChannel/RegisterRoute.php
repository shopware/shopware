<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 * @ContextTokenRequired()
 */
class RegisterRoute extends AbstractRegisterRoute
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
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    /**
     * @var DataValidationFactoryInterface
     */
    private $addressValidationFactory;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var DataValidationFactoryInterface
     */
    private $accountValidationFactory;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        DataValidator $validator,
        DataValidationFactoryInterface $accountValidationFactory,
        DataValidationFactoryInterface $addressValidationFactory,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $customerRepository,
        SalesChannelContextPersister $contextPersister
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->validator = $validator;
        $this->accountValidationFactory = $accountValidationFactory;
        $this->addressValidationFactory = $addressValidationFactory;
        $this->systemConfigService = $systemConfigService;
        $this->customerRepository = $customerRepository;
        $this->contextPersister = $contextPersister;
    }

    public function getDecorated(): AbstractRegisterRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/register",
     *      description="Register",
     *      operationId="register",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(name="guest", description="Create guest user", in="query", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="title", description="Title", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="salutationId", description="Salutation", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="firstName", description="Firstname", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="lastName", description="Lastname", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="email", description="email", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="affiliateCode", description="Affilicate Code", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="campaignCode", description="Campaign Code", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="password", description="Password", in="query", @OA\Schema(type="string")),
     *      @OA\Parameter(name="billingAddress", description="Billingaddress", in="query", @OA\JsonContent(ref="#/components/schemas/customer_address_flat")),
     *      @OA\Parameter(name="shippingAddress", description="Shippingaddress", in="query", @OA\JsonContent(ref="#/components/schemas/customer_address_flat")),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(ref="#/components/schemas/customer_flat")
     *     )
     * )
     * @Route("/store-api/v{version}/account/register", name="store-api.account.register", methods={"POST"})
     */
    public function register(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): CustomerResponse
    {
        $isGuest = $data->getBoolean('guest');
        $this->validateRegistrationData($data, $isGuest, $context, $additionalValidationDefinitions, $validateStorefrontUrl);

        $customer = $this->mapCustomerData($data, $isGuest, $context);

        /** @var DataBag $billing */
        $billing = $data->get('billingAddress');

        if ($data->has('title')) {
            $billing->set('title', $data->get('title'));
        }

        $billingAddress = $this->mapBillingAddress($billing, $context->getContext());
        $billingAddress['id'] = Uuid::randomHex();
        $billingAddress['customerId'] = $customer['id'];

        // if no shipping address is provided, use the billing address
        $customer['defaultShippingAddressId'] = $billingAddress['id'];
        $customer['defaultBillingAddressId'] = $billingAddress['id'];
        $customer['addresses'][] = $billingAddress;

        if ($shipping = $data->get('shippingAddress')) {
            $shippingAddress = $this->mapShippingAddress($shipping, $context->getContext());
            $shippingAddress['id'] = Uuid::randomHex();
            $shippingAddress['customerId'] = $customer['id'];

            $customer['defaultShippingAddressId'] = $shippingAddress['id'];
            $customer['addresses'][] = $shippingAddress;
        }

        if ($data->get('accountType') === CustomerEntity::ACCOUNT_TYPE_BUSINESS && !empty($billingAddress['company'])) {
            $customer['company'] = $billingAddress['company'];
        }

        $customer = $this->setDoubleOptInData($customer, $context);

        if (Feature::isActive('FEATURE_NEXT_10555')) {
            $customer['boundSalesChannelId'] = $this->getBoundSalesChannelId($customer['email'], $context);
        }

        $this->customerRepository->create([$customer], $context->getContext());

        $criteria = new Criteria([$customer['id']]);
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('salutation');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->customerRepository->search($criteria, $context->getContext())->first();

        if ($customerEntity->getDoubleOptInRegistration()) {
            $this->eventDispatcher->dispatch($this->getDoubleOptInEvent($customerEntity, $context, $data->get('storefrontUrl')));
        } elseif (!$customerEntity->getGuest()) {
            $this->eventDispatcher->dispatch(new CustomerRegisterEvent($context, $customerEntity));
        } else {
            $this->eventDispatcher->dispatch(new GuestCustomerRegisterEvent($context, $customerEntity));
        }

        $response = new CustomerResponse($customerEntity);

        if (!$customerEntity->getDoubleOptInRegistration()) {
            $newToken = $this->contextPersister->replace($context->getToken(), $context);
            $this->contextPersister->save(
                $newToken,
                [
                    'customerId' => $customerEntity->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ],
                Feature::isActive('FEATURE_NEXT_10058') ? $customerEntity->getId() : null
            );

            $event = new CustomerLoginEvent($context, $customerEntity, $newToken);
            $this->eventDispatcher->dispatch($event);

            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);
        }

        // We don't want to leak the hash in store-api
        $customerEntity->setHash('');

        return $response;
    }

    private function getDoubleOptInEvent(CustomerEntity $customer, SalesChannelContext $context, string $url): Event
    {
        $url .= sprintf('/registration/confirm?em=%s&hash=%s', hash('sha1', $customer->getEmail()), $customer->getHash());

        if ($customer->getGuest()) {
            $event = new DoubleOptInGuestOrderEvent($customer, $context, $url);
        } else {
            $event = new CustomerDoubleOptInRegistrationEvent($customer, $context, $url);
        }

        return $event;
    }

    private function setDoubleOptInData(array $customer, SalesChannelContext $context): array
    {
        $configKey = $customer['guest']
            ? 'core.loginRegistration.doubleOptInGuestOrder'
            : 'core.loginRegistration.doubleOptInRegistration';

        $doubleOptInRequired = $this->systemConfigService
            ->get($configKey, $context->getSalesChannel()->getId());

        if (!$doubleOptInRequired) {
            return $customer;
        }

        $customer['active'] = false;
        $customer['doubleOptInRegistration'] = true;
        $customer['doubleOptInEmailSentDate'] = new \DateTimeImmutable();
        $customer['hash'] = Uuid::randomHex();

        return $customer;
    }

    private function validateRegistrationData(DataBag $data, bool $isGuest, SalesChannelContext $context, ?DataValidationDefinition $additionalValidations, bool $validateStorefrontUrl): void
    {
        /** @var DataBag $addressData */
        $addressData = $data->get('billingAddress');
        $addressData->set('firstName', $data->get('firstName'));
        $addressData->set('lastName', $data->get('lastName'));
        $addressData->set('salutationId', $data->get('salutationId'));

        $definition = $this->getCustomerCreateValidationDefinition($isGuest, $context);

        if ($additionalValidations) {
            foreach ($additionalValidations->getProperties() as $key => $validation) {
                $definition->add($key, ...$validation);
            }
        }

        if ($validateStorefrontUrl) {
            $definition
                ->add('storefrontUrl', new NotBlank(), new Choice(array_values($this->getDomainUrls($context))));
        }

        $accountType = $data->get('accountType', CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $definition->addSub('billingAddress', $this->getCreateAddressValidationDefinition($accountType, true, $context));

        if ($data->has('shippingAddress')) {
            $definition->addSub('shippingAddress', $this->getCreateAddressValidationDefinition($accountType, false, $context));
        }

        $violations = $this->validator->getViolations($data->all(), $definition);
        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }

    private function getDomainUrls(SalesChannelContext $context): array
    {
        return array_map(static function (SalesChannelDomainEntity $domainEntity) {
            return $domainEntity->getUrl();
        }, $context->getSalesChannel()->getDomains()->getElements());
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

    private function mapBillingAddress(DataBag $billing, Context $context): array
    {
        $billingAddress = $this->mapAddressData($billing);

        $event = new DataMappingEvent($billing, $billingAddress, $context);
        $this->eventDispatcher->dispatch($event, CustomerEvents::MAPPING_REGISTER_ADDRESS_BILLING);

        return $event->getOutput();
    }

    private function mapShippingAddress(DataBag $shipping, Context $context): array
    {
        $shippingAddress = $this->mapAddressData($shipping);

        $event = new DataMappingEvent($shipping, $shippingAddress, $context);
        $this->eventDispatcher->dispatch($event, CustomerEvents::MAPPING_REGISTER_ADDRESS_SHIPPING);

        return $event->getOutput();
    }

    private function mapCustomerData(DataBag $data, bool $isGuest, SalesChannelContext $context): array
    {
        $customer = [
            'customerNumber' => $this->numberRangeValueGenerator->getValue(
                $this->customerRepository->getDefinition()->getEntityName(),
                $context->getContext(),
                $context->getSalesChannel()->getId()
            ),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'languageId' => $context->getContext()->getLanguageId(),
            'groupId' => $context->getCurrentCustomerGroup()->getId(),
            'requestedGroupId' => $data->get('requestedGroupId', null),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'salutationId' => $data->get('salutationId'),
            'firstName' => $data->get('firstName'),
            'lastName' => $data->get('lastName'),
            'email' => $data->get('email'),
            'title' => $data->get('title'),
            'affiliateCode' => $data->get(OrderService::AFFILIATE_CODE_KEY),
            'campaignCode' => $data->get(OrderService::CAMPAIGN_CODE_KEY),
            'active' => true,
            'birthday' => $this->getBirthday($data),
            'guest' => $isGuest,
            'firstLogin' => new \DateTimeImmutable(),
            'addresses' => [],
        ];

        if (!$isGuest) {
            $customer['password'] = $data->get('password');
        }

        $event = new DataMappingEvent($data, $customer, $context->getContext());
        $this->eventDispatcher->dispatch($event, CustomerEvents::MAPPING_REGISTER_CUSTOMER);

        $customer = $event->getOutput();
        $customer['id'] = Uuid::randomHex();

        return $customer;
    }

    private function getCreateAddressValidationDefinition(string $accountType, bool $isBillingAddress, SalesChannelContext $context): DataValidationDefinition
    {
        $validation = $this->addressValidationFactory->create($context);

        if ($isBillingAddress
            && $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS
            && $this->systemConfigService->get('core.loginRegistration.showAccountTypeSelection', $context->getSalesChannel()->getId())) {
            $validation->add('company', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($validation, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    private function getCustomerCreateValidationDefinition(bool $isGuest, SalesChannelContext $context): DataValidationDefinition
    {
        $validation = $this->accountValidationFactory->create($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('registrationSalesChannels.id', $context->getSalesChannel()->getId()));

        $validation->add('requestedGroupId', new EntityExists([
            'entity' => 'customer_group',
            'context' => $context->getContext(),
            'criteria' => $criteria,
        ]));

        if (!$isGuest) {
            $minLength = $this->systemConfigService->get('core.loginRegistration.passwordMinLength', $context->getSalesChannel()->getId());
            $validation->add('password', new NotBlank(), new Length(['min' => $minLength]));
            $options = Feature::isActive('FEATURE_NEXT_10555')
                ? ['context' => $context->getContext(), 'salesChannelContext' => $context]
                : ['context' => $context->getContext()];

            $validation->add('email', new CustomerEmailUnique($options));
        }

        $validationEvent = new BuildValidationEvent($validation, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    private function mapAddressData(DataBag $addressData): array
    {
        $mappedData = $addressData->only(
            'firstName',
            'lastName',
            'salutationId',
            'street',
            'zipcode',
            'city',
            'company',
            'department',
            'vatId',
            'countryStateId',
            'countryId',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'phoneNumber'
        );

        if (isset($mappedData['countryStateId']) && $mappedData['countryStateId'] === '') {
            $mappedData['countryStateId'] = null;
        }

        return $mappedData;
    }

    private function getBoundSalesChannelId(string $email, SalesChannelContext $context): ?string
    {
        $bindCustomers = $this->systemConfigService->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
        $salesChannelId = $context->getSalesChannel()->getId();

        if ($bindCustomers) {
            return $salesChannelId;
        }

        if ($this->hasBoundAccount($email, $context)) {
            return $salesChannelId;
        }

        return null;
    }

    private function hasBoundAccount(string $email, SalesChannelContext $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('customer.boundSalesChannelId', null),
        ]));

        $criteria->setLimit(1);

        return $this->customerRepository->search($criteria, $context->getContext())->count() > 0;
    }
}
