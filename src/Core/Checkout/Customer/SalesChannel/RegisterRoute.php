<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerConfirmRegisterUrlEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
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
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\Exception\CountryNotFoundException;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
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

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $countryRepository;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        DataValidator $validator,
        DataValidationFactoryInterface $accountValidationFactory,
        DataValidationFactoryInterface $addressValidationFactory,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $customerRepository,
        SalesChannelContextPersister $contextPersister,
        SalesChannelRepositoryInterface $countryRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->validator = $validator;
        $this->accountValidationFactory = $accountValidationFactory;
        $this->addressValidationFactory = $addressValidationFactory;
        $this->systemConfigService = $systemConfigService;
        $this->customerRepository = $customerRepository;
        $this->contextPersister = $contextPersister;
        $this->countryRepository = $countryRepository;
    }

    public function getDecorated(): AbstractRegisterRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/account/register",
     *      summary="Register a customer",
     *      description="Registers a customer. Used both for normal customers and guest customers.

See the Guide ""Register a customer"" for more information on customer registration.",
     *      operationId="register",
     *      tags={"Store API", "Login & Registration"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "email",
     *                  "password",
     *                  "salutationId",
     *                  "firstName",
     *                  "lastName",
     *                  "acceptedDataProtection",
     *                  "storefrontUrl",
     *                  "billingAddress"
     *              },
     *              @OA\Property(
     *                  property="email",
     *                  type="string",
     *                  description="Email of the customer. Has to be unique, unless `guest` is `true`"),
     *              @OA\Property(
     *                  property="password",
     *                  type="string",
     *                  description="Password for the customer. Required, unless `guest` is `true`"),
     *              @OA\Property(
     *                  property="salutationId",
     *                  type="string",
     *                  description="Id of the salutation for the customer account. Fetch options using `salutation` endpoint."),
     *              @OA\Property(
     *                  property="firstName",
     *                  type="string",
     *                  description="Customer first name. Value will be reused for shipping and billing address if not provided explicitly."),
     *              @OA\Property(
     *                  property="lastName",
     *                  type="string",
     *                  description="Customer last name. Value will be reused for shipping and billing address if not provided explicitly."),
     *              @OA\Property(
     *                  property="acceptedDataProtection",
     *                  type="boolean",
     *                  description="Flag indicating accepted data protection"),
     *              @OA\Property(
     *                  property="storefrontUrl",
     *                  type="string",
     *                  description="URL of the storefront for that registration. Used in confirmation emails. Has to be one of the configured domains of the sales channel."),
     *              @OA\Property(
     *                  property="billingAddress",
     *                  ref="#/components/schemas/CustomerAddress",
     *                  description="Billing address of the customer. Values will be reused for shipping address if not provided explicitly."),
     *              @OA\Property(
     *                  property="shippingAddress",
     *                  ref="#/components/schemas/CustomerAddress",
     *                  description="Shipping address of the customer. If not set, billing address will be used."),
     *              @OA\Property(
     *                  property="accountType",
     *                  type="string",
     *                  default="private",
     *                  description="Account type of the customer which can be either `private` or `business`."),
     *              @OA\Property(
     *                  property="guest",
     *                  type="boolean",
     *                  default=false,
     *                  description="If set, will create a guest customer. Guest customers can re-use an email address and don't need a password."),
     *              @OA\Property(
     *                  property="birthdayDay",
     *                  type="integer",
     *                  description="Birthday day"),
     *              @OA\Property(
     *                  property="birthdayMonth",
     *                  type="integer",
     *                  description="Birthday month"),
     *              @OA\Property(
     *                  property="birthdayYear",
     *                  type="integer",
     *                  description="Birthday year"),
     *              @OA\Property(
     *                  property="title",
     *                  type="string",
     *                  description="(Academic) title of the customer"),
     *              @OA\Property(
     *                  property="affiliateCode",
     *                  type="string",
     *                  description="Field can be used to store an affiliate tracking code"),
     *              @OA\Property(
     *                  property="campaignCode",
     *                  type="string",
     *                  description="Field can be used to store a campaign tracking code")
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(ref="#/components/schemas/Customer")
     *     )
     * )
     * @Route("/store-api/account/register", name="store-api.account.register", methods={"POST"})
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

            if ($data->get('vatIds')) {
                $vatIds = $data->get('vatIds');
                $customer['vatIds'] = empty($vatIds) ? null : $vatIds;
            }
        }

        $customer = $this->setDoubleOptInData($customer, $context);

        $customer['boundSalesChannelId'] = $this->getBoundSalesChannelId($customer['email'], $context);

        $this->customerRepository->create([$customer], $context->getContext());

        $criteria = new Criteria([$customer['id']]);
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('salutation');
        $criteria->addAssociation('defaultBillingAddress');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->customerRepository->search($criteria, $context->getContext())->first();

        if ($customerEntity->getDoubleOptInRegistration()) {
            $this->eventDispatcher->dispatch($this->getDoubleOptInEvent($customerEntity, $context, $data->get('storefrontUrl'), $data->get('redirectTo')));
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
                $context->getSalesChannel()->getId(),
                $customerEntity->getId()
            );

            $event = new CustomerLoginEvent($context, $customerEntity, $newToken);
            $this->eventDispatcher->dispatch($event);

            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);
        }

        // We don't want to leak the hash in store-api
        $customerEntity->setHash('');

        return $response;
    }

    private function getDoubleOptInEvent(CustomerEntity $customer, SalesChannelContext $context, string $url, ?string $redirectTo = null): Event
    {
        $url .= $this->getConfirmUrl($context, $customer);

        if ($redirectTo) {
            $url .= '&redirectTo=' . $redirectTo;
        }

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

        $definition = $this->getCustomerCreateValidationDefinition($isGuest, $data, $context);

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
        $definition->addSub('billingAddress', $this->getCreateAddressValidationDefinition($data, $accountType, true, $context));

        if ($data->has('shippingAddress')) {
            $shippingAccountType = $data->get('shippingAddress')->get('accountType', CustomerEntity::ACCOUNT_TYPE_PRIVATE);
            $definition->addSub('shippingAddress', $this->getCreateAddressValidationDefinition($data, $shippingAccountType, true, $context));
        }

        $billingAddress = $addressData->all();

        if ($data->get('vatIds') instanceof DataBag) {
            $vatIds = array_filter($data->get('vatIds')->all());
            $data->set('vatIds', $vatIds);
        }

        if ($data->get('vatIds') !== null && $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            //@internal (flag:FEATURE_NEXT_14114) Remove with feature flag
            if (!Feature::isActive('FEATURE_NEXT_14114') && $this->systemConfigService->get('core.loginRegistration.vatIdFieldRequired', $context->getSalesChannel()->getId())) {
                $definition->add('vatIds', new NotBlank());
            }

            if (Feature::isActive('FEATURE_NEXT_14114') && $this->requiredVatIdField($billingAddress['countryId'], $context)) {
                $definition->add('vatIds', new NotBlank());
            }

            $definition->add('vatIds', new Type('array'), new CustomerVatIdentification(
                ['countryId' => $billingAddress['countryId']]
            ));
        }

        if ($this->systemConfigService->get('core.loginRegistration.requireDataProtectionCheckbox', $context->getSalesChannel()->getId())) {
            $definition->add('acceptedDataProtection', new NotBlank());
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
            return rtrim($domainEntity->getUrl(), '/');
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

    private function getCreateAddressValidationDefinition(DataBag $data, string $accountType, bool $isBillingAddress, SalesChannelContext $context): DataValidationDefinition
    {
        $validation = $this->addressValidationFactory->create($context);

        if ($isBillingAddress
            && $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS
            && $this->systemConfigService->get('core.loginRegistration.showAccountTypeSelection', $context->getSalesChannel()->getId())) {
            $validation->add('company', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    private function getCustomerCreateValidationDefinition(bool $isGuest, DataBag $data, SalesChannelContext $context): DataValidationDefinition
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
            $options = ['context' => $context->getContext(), 'salesChannelContext' => $context];
            $validation->add('email', new CustomerEmailUnique($options));
        }

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
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

    private function requiredVatIdField(string $countryId, SalesChannelContext $context): bool
    {
        /** @var CountryEntity|null $country */
        $country = $this->countryRepository->search(new Criteria([$countryId]), $context)->get($countryId);

        if (!$country) {
            throw new CountryNotFoundException($countryId);
        }

        return $country->getVatIdRequired();
    }

    private function getConfirmUrl(SalesChannelContext $context, CustomerEntity $customer): string
    {
        $urlTemplate = $this->systemConfigService->get(
            'core.loginRegistration.confirmationUrl',
            $context->getSalesChannelId()
        );
        if (!\is_string($urlTemplate)) {
            $urlTemplate = '/registration/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%';
        }

        $emailHash = hash('sha1', $customer->getEmail());

        $urlEvent = new CustomerConfirmRegisterUrlEvent($context, $urlTemplate, $emailHash, $customer->getHash(), $customer);
        $this->eventDispatcher->dispatch($urlEvent);

        return str_replace(
            ['%%HASHEDEMAIL%%', '%%SUBSCRIBEHASH%%'],
            [$emailHash, $customer->getHash()],
            $urlEvent->getConfirmUrl()
        );
    }
}
