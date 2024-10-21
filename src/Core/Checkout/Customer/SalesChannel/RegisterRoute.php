<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerConfirmRegisterUrlEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class RegisterRoute extends AbstractRegisterRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param SalesChannelRepository<CountryCollection> $countryRepository
     * @param EntityRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly DataValidator $validator,
        private readonly DataValidationFactoryInterface $accountValidationFactory,
        private readonly DataValidationFactoryInterface $addressValidationFactory,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $customerRepository,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly SalesChannelRepository $countryRepository,
        protected Connection $connection,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly StoreApiCustomFieldMapper $customFieldMapper,
        private readonly EntityRepository $salutationRepository,
    ) {
    }

    public function getDecorated(): AbstractRegisterRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/register', name: 'store-api.account.register', methods: ['POST'])]
    public function register(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): CustomerResponse
    {
        EmailIdnConverter::encodeDataBag($data);

        $isGuest = $data->getBoolean('guest');

        if ($data->has('accountType') && empty($data->get('accountType'))) {
            $data->remove('accountType');
        }

        if (!$data->get('salutationId')) {
            $data->set('salutationId', $this->getDefaultSalutationId($context));
        }

        $billing = $data->get('billingAddress');
        $shipping = $data->get('shippingAddress');

        if ($billing instanceof DataBag) {
            if (Feature::isActive('v6.7.0.0')) {
                if ($billing->has('firstName') && !$data->has('firstName')) {
                    $data->set('firstName', $billing->get('firstName'));
                }

                if ($billing->has('lastName') && !$data->has('lastName')) {
                    $data->set('lastName', $billing->get('lastName'));
                }
            }

            if ($data->has('title')) {
                $billing->set('title', $data->get('title'));
            }
        }

        $this->validateRegistrationData($data, $isGuest, $context, $additionalValidationDefinitions, $validateStorefrontUrl);

        $customer = $this->mapCustomerData($data, $isGuest, $context);

        if ($billing instanceof DataBag) {
            $billingAddress = $this->mapAddressData($billing, $context->getContext(), CustomerEvents::MAPPING_REGISTER_ADDRESS_BILLING);
            $billingAddress['id'] = Uuid::randomHex();
            $billingAddress['customerId'] = $customer['id'];
            $customer['defaultBillingAddressId'] = $billingAddress['id'];
            $customer['addresses'][] = $billingAddress;

            if (!$shipping) {
                $customer['defaultShippingAddressId'] = $billingAddress['id'];
            }
        }

        if ($shipping instanceof DataBag) {
            $shippingAddress = $this->mapAddressData($shipping, $context->getContext(), CustomerEvents::MAPPING_REGISTER_ADDRESS_SHIPPING);
            $shippingAddress['id'] = Uuid::randomHex();
            $shippingAddress['customerId'] = $customer['id'];

            $customer['defaultShippingAddressId'] = $shippingAddress['id'];
            $customer['addresses'][] = $shippingAddress;

            if (!$billing) {
                $customer['defaultBillingAddressId'] = $shippingAddress['id'];
            }
        }

        if ($data->get('accountType')) {
            $customer['accountType'] = $data->get('accountType');
        }

        $companyName = $billingAddress['company'] ?? $shippingAddress['company'] ?? null;
        if ($data->get('accountType') === CustomerEntity::ACCOUNT_TYPE_BUSINESS && $companyName) {
            $customer['company'] = $companyName;
            if ($data->get('vatIds')) {
                $customer['vatIds'] = $data->get('vatIds');
            }
        }

        $customer = $this->addDoubleOptInData($customer, $context);

        $customer['boundSalesChannelId'] = $this->getBoundSalesChannelId($customer['email'], $context);

        if ($data->get('customFields') instanceof RequestDataBag) {
            $customer['customFields'] = $this->customFieldMapper->map(CustomerDefinition::ENTITY_NAME, $data->get('customFields'));
        }

        // Convert all DataBags to array
        $customer = array_map(static function (mixed $value) {
            if ($value instanceof DataBag) {
                return $value->all();
            }

            return $value;
        }, $customer);

        $this->customerRepository->create([$customer], $context->getContext());

        $criteria = new Criteria([$customer['id']]);
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('salutation');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('defaultBillingAddress.countryState');
        $criteria->addAssociation('defaultBillingAddress.salutation');
        $criteria->addAssociation('defaultShippingAddress.country');
        $criteria->addAssociation('defaultShippingAddress.countryState');
        $criteria->addAssociation('defaultShippingAddress.salutation');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->customerRepository->search($criteria, $context->getContext())->first();

        if ($customerEntity->getDoubleOptInRegistration()) {
            $this->eventDispatcher->dispatch(
                $this->getDoubleOptInEvent(
                    $customerEntity,
                    $context,
                    $data->get('storefrontUrl'),
                    $data->get('redirectTo'),
                    $data->get('redirectParameters')
                )
            );

            // We don't want to leak the hash in store-api
            $customerEntity->setHash('');

            return new CustomerResponse($customerEntity);
        }

        $response = new CustomerResponse($customerEntity);

        $newToken = $this->contextPersister->replace($context->getToken(), $context);

        $this->contextPersister->save(
            $newToken,
            [
                'customerId' => $customerEntity->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
                'domainId' => $context->getDomainId(),
            ],
            $context->getSalesChannel()->getId(),
            $customerEntity->getId()
        );

        $new = $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $context->getSalesChannel()->getId(),
                $newToken,
                $context->getLanguageId(),
                $context->getCurrencyId(),
                $context->getDomainId(),
                $context->getContext(),
                $customerEntity->getId()
            )
        );

        $new->addState(...$context->getStates());

        if (!$customerEntity->getGuest()) {
            $this->eventDispatcher->dispatch(new CustomerRegisterEvent($new, $customerEntity));
        } else {
            $this->eventDispatcher->dispatch(new GuestCustomerRegisterEvent($new, $customerEntity));
        }

        $event = new CustomerLoginEvent($new, $customerEntity, $newToken);
        $this->eventDispatcher->dispatch($event);

        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

        // We don't want to leak the hash in store-api
        $customerEntity->setHash('');

        return $response;
    }

    private function getDoubleOptInEvent(
        CustomerEntity $customer,
        SalesChannelContext $context,
        string $url,
        ?string $redirectTo,
        ?string $redirectParameters
    ): Event {
        $url .= $this->getConfirmUrl($context, $customer);

        if ($redirectTo) {
            $params = \is_string($redirectParameters) ? (\json_decode($redirectParameters, true) ?? []) : [];
            $url .= '&' . \http_build_query(array_merge(['redirectTo' => $redirectTo], $params));
        }

        if ($customer->getGuest()) {
            $event = new DoubleOptInGuestOrderEvent($customer, $context, $url);
        } else {
            $event = new CustomerDoubleOptInRegistrationEvent($customer, $context, $url);
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $customer
     *
     * @return array<string, mixed>
     */
    private function addDoubleOptInData(array $customer, SalesChannelContext $context): array
    {
        $configKey = $customer['guest']
            ? 'core.loginRegistration.doubleOptInGuestOrder'
            : 'core.loginRegistration.doubleOptInRegistration';

        $doubleOptInRequired = $this->systemConfigService
            ->get($configKey, $context->getSalesChannelId());

        if (!$doubleOptInRequired) {
            return $customer;
        }

        $customer['doubleOptInRegistration'] = true;
        $customer['doubleOptInEmailSentDate'] = new \DateTimeImmutable();
        $customer['hash'] = Uuid::randomHex();

        return $customer;
    }

    private function validateRegistrationData(DataBag $data, bool $isGuest, SalesChannelContext $context, ?DataValidationDefinition $additionalValidations, bool $validateStorefrontUrl): void
    {
        $billingAddress = $data->get('billingAddress');
        $shippingAddress = $data->get('shippingAddress');
        if ($billingAddress instanceof DataBag) {
            $billingAddress->set('firstName', $data->get('firstName'));
            $billingAddress->set('lastName', $data->get('lastName'));
            $billingAddress->set('salutationId', $data->get('salutationId'));
        }

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
        if ($billingAddress instanceof DataBag || !($shippingAddress instanceof DataBag)) {
            $definition->addSub('billingAddress', $this->getCreateAddressValidationDefinition($data, $accountType, $billingAddress ?? new RequestDataBag(), $context));
        }

        if ($shippingAddress instanceof DataBag) {
            $shippingAccountType = $shippingAddress->get('accountType', CustomerEntity::ACCOUNT_TYPE_PRIVATE);
            $definition->addSub('shippingAddress', $this->getCreateAddressValidationDefinition($data, $shippingAccountType, $shippingAddress, $context));
        }

        if ($data->get('vatIds') instanceof DataBag) {
            $vatIds = array_filter($data->get('vatIds')->all());
            $data->set('vatIds', $vatIds);
        }

        if ($accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            $countryId = $shippingAddress instanceof DataBag
                ? $shippingAddress->get('countryId')
                : ($billingAddress instanceof DataBag ? $billingAddress->get('countryId') : null);

            if ($countryId) {
                if ($this->requiredVatIdField($countryId, $context)) {
                    $definition->add('vatIds', new NotBlank());
                }

                $definition->add('vatIds', new Type('array'), new CustomerVatIdentification(
                    ['countryId' => $countryId]
                ));
            }
        }

        if ($this->systemConfigService->get('core.loginRegistration.requireDataProtectionCheckbox', $context->getSalesChannelId())) {
            $definition->add('acceptedDataProtection', new NotBlank());
        }

        $violations = $this->validator->getViolations($data->all(), $definition);

        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }

    /**
     * @return array<int, string>
     */
    private function getDomainUrls(SalesChannelContext $context): array
    {
        /** @var SalesChannelDomainCollection $salesChannelDomainCollection */
        $salesChannelDomainCollection = $context->getSalesChannel()->getDomains();

        return array_map(static fn (SalesChannelDomainEntity $domainEntity) => rtrim($domainEntity->getUrl(), '/'), $salesChannelDomainCollection->getElements());
    }

    private function getBirthday(DataBag $data): ?\DateTimeInterface
    {
        $birthdayDay = $data->get('birthdayDay');
        $birthdayMonth = $data->get('birthdayMonth');
        $birthdayYear = $data->get('birthdayYear');

        if (!$birthdayDay || !$birthdayMonth || !$birthdayYear) {
            return null;
        }
        \assert(\is_numeric($birthdayDay));
        \assert(\is_numeric($birthdayMonth));
        \assert(\is_numeric($birthdayYear));

        return new \DateTime(\sprintf(
            '%d-%d-%d',
            $birthdayYear,
            $birthdayMonth,
            $birthdayDay
        ));
    }

    /**
     * @return array<string, mixed>
     */
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

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $context->getPaymentMethod()->getId();
        }

        if (!$isGuest) {
            $customer['password'] = $data->get('password');
        }

        $event = new DataMappingEvent($data, $customer, $context->getContext());
        $this->eventDispatcher->dispatch($event, CustomerEvents::MAPPING_REGISTER_CUSTOMER);

        $customer = $event->getOutput();
        $customer['id'] = Uuid::randomHex();

        return $customer;
    }

    private function getCreateAddressValidationDefinition(DataBag $data, ?string $accountType, DataBag $address, SalesChannelContext $context): DataValidationDefinition
    {
        $validation = $this->addressValidationFactory->create($context);

        if ($accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS
            && $this->systemConfigService->get('core.loginRegistration.showAccountTypeSelection', $context->getSalesChannelId())) {
            $validation->add('company', new NotBlank());
        }

        $validation->set('zipcode', new CustomerZipCode(['countryId' => $address->get('countryId')]));
        $validation->add('zipcode', new Length(['max' => 50]));

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
            $minLength = $this->systemConfigService->get('core.loginRegistration.passwordMinLength', $context->getSalesChannelId());
            $validation->add('password', new NotBlank(), new Length(['min' => $minLength]));
            $options = ['context' => $context->getContext(), 'salesChannelContext' => $context];
            $validation->add('email', new CustomerEmailUnique($options));
        }

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAddressData(DataBag $addressData, Context $context, string $eventName): array
    {
        $mappedData = $addressData->only(
            'title',
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

        if ($addressData->get('customFields') instanceof RequestDataBag) {
            $mappedData['customFields'] = $this->customFieldMapper->map(CustomerAddressDefinition::ENTITY_NAME, $addressData->get('customFields'));
        }

        $event = new DataMappingEvent($addressData, $mappedData, $context);
        $this->eventDispatcher->dispatch($event, $eventName);

        return $event->getOutput();
    }

    private function getBoundSalesChannelId(string $email, SalesChannelContext $context): ?string
    {
        $bindCustomers = $this->systemConfigService->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
        $salesChannelId = $context->getSalesChannelId();

        if ($bindCustomers) {
            return $salesChannelId;
        }

        if ($this->hasBoundAccount($email)) {
            return $salesChannelId;
        }

        return null;
    }

    private function hasBoundAccount(string $email): bool
    {
        $query = $this->connection->createQueryBuilder();

        /** @var array{email: string, guest: int, bound_sales_channel_id: string|null}[] $results */
        $results = $query
            ->select('LOWER(HEX(bound_sales_channel_id)) as bound_sales_channel_id')
            ->from('customer')
            ->where($query->expr()->eq('email', $query->createPositionalParameter($email)))
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $result) {
            if ($result['bound_sales_channel_id']) {
                return true;
            }
        }

        return false;
    }

    private function requiredVatIdField(string $countryId, SalesChannelContext $context): bool
    {
        $country = $this->countryRepository->search(new Criteria([$countryId]), $context)->get($countryId);

        if (!$country instanceof CountryEntity) {
            throw CustomerException::countryNotFound($countryId);
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

        $emailHash = Hasher::hash($customer->getEmail(), 'sha1');

        $urlEvent = new CustomerConfirmRegisterUrlEvent($context, $urlTemplate, $emailHash, $customer->getHash(), $customer);
        $this->eventDispatcher->dispatch($urlEvent);

        return str_replace(
            ['%%HASHEDEMAIL%%', '%%SUBSCRIBEHASH%%'],
            [$emailHash, $customer->getHash()],
            $urlEvent->getConfirmUrl()
        );
    }

    private function getDefaultSalutationId(SalesChannelContext $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(
            new EqualsFilter('salutationKey', SalutationDefinition::NOT_SPECIFIED)
        );

        return $this->salutationRepository->searchIds($criteria, $context->getContext())->firstId();
    }
}
