<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class UpsertAddressRoute extends AbstractUpsertAddressRoute
{
    use CustomerAddressDataNormalizerTrait;
    use CustomerAddressValidationTrait;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerAddressCollection> $addressRepository
     * @param SalesChannelRepository<CustomerAddressCollection> $salesChannelAddressRepository
     * @param EntityRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly EntityRepository $addressRepository,
        private readonly SalesChannelRepository $salesChannelAddressRepository,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidationFactoryInterface $addressValidationFactory,
        private readonly SystemConfigService $systemConfigService,
        private readonly StoreApiCustomFieldMapper $storeApiCustomFieldMapper,
        private readonly EntityRepository $salutationRepository,
    ) {
    }

    public function getDecorated(): AbstractUpsertAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/address',
        name: 'store-api.account.address.create',
        defaults: [
            'addressId' => null,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    #[Route(
        path: '/store-api/account/address/{addressId}',
        name: 'store-api.account.address.update',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function upsert(
        ?string $addressId,
        RequestDataBag $data,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): UpsertAddressRouteResponse {
        if (!$addressId) {
            $isCreate = true;
            $addressId = Uuid::randomHex();
        } else {
            $this->validateAddress($addressId, $context, $customer);
            $isCreate = false;
        }

        if (!$data->get('salutationId')) {
            $data->set('salutationId', $this->getDefaultSalutationId($context));
        }

        $accountType = $data->get('accountType');

        if (!\is_string($accountType) || $accountType === '') {
            $accountType = null;
        }

        // Anything the request names other than business counts as private, because that is how the
        // branch below reads it and there is no Choice constraint to keep an unknown value out.
        $namesAreOptional = $customer->isBusinessAccount()
            && ($accountType === null || $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS)
            && !CompanyAccountNameFields::areRequired($this->systemConfigService, $context->getSalesChannelId());

        if ($namesAreOptional) {
            // Only an update has stored names to keep; a create carries an id nothing is saved under.
            if (!$isCreate) {
                $this->keepStoredNames($addressId, $data, $context);
            }

            CompanyAccountNameFields::normalize($data);
        }

        $definition = $this->getValidationDefinition($data, $accountType, $namesAreOptional, $isCreate, $context);
        $this->validator->validate(array_merge(['id' => $addressId], $data->all()), $definition);

        $addressData = [
            'salutationId' => $data->get('salutationId'),
            'firstName' => $data->get('firstName'),
            'lastName' => $data->get('lastName'),
            'street' => $data->get('street'),
            'city' => $data->get('city'),
            'zipcode' => $data->get('zipcode'),
            'countryId' => $data->get('countryId'),
            'countryStateId' => $data->get('countryStateId') ?: null,
            'company' => $data->get('company'),
            'department' => $data->get('department'),
            'title' => $data->get('title'),
            'phoneNumber' => $data->get('phoneNumber'),
            'additionalAddressLine1' => $data->get('additionalAddressLine1'),
            'additionalAddressLine2' => $data->get('additionalAddressLine2'),
        ];

        $addressData = $this->trimAddressFields($addressData);

        if ($data->get('customFields') instanceof RequestDataBag) {
            $addressData['customFields'] = $this->storeApiCustomFieldMapper->map(
                CustomerAddressDefinition::ENTITY_NAME,
                $data->get('customFields')
            );
            if ($addressData['customFields'] === []) {
                unset($addressData['customFields']);
            }
        }

        $mappingEvent = new DataMappingEvent($data, $addressData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_ADDRESS_CREATE);

        $addressData = $mappingEvent->getOutput();
        $addressData['id'] = $addressId;
        $addressData['customerId'] = $customer->getId();

        $this->addressRepository->upsert([$addressData], $context->getContext());

        $address = $this->salesChannelAddressRepository->search(new Criteria([$addressId]), $context)->getEntities()->first();
        \assert($address !== null);

        return new UpsertAddressRouteResponse($address);
    }

    /**
     * The address data below takes both names from the request every time. A form that hides them
     * submits neither, so an edit of the street alone would replace a stored contact person with an
     * empty string. Reading the stored values back first keeps that edit harmless.
     */
    private function keepStoredNames(?string $addressId, DataBag $data, SalesChannelContext $context): void
    {
        if ($addressId === null || ($data->has('firstName') && $data->has('lastName'))) {
            return;
        }

        $address = $this->addressRepository
            ->search(new Criteria([$addressId]), $context->getContext())
            ->getEntities()
            ->first();

        if (!$address instanceof CustomerAddressEntity) {
            return;
        }

        if (!$data->has('firstName')) {
            $data->set('firstName', $address->getFirstName());
        }

        if (!$data->has('lastName')) {
            $data->set('lastName', $address->getLastName());
        }
    }

    private function getValidationDefinition(
        DataBag $data,
        ?string $accountType,
        bool $namesAreOptional,
        bool $isCreate,
        SalesChannelContext $context
    ): DataValidationDefinition {
        if ($isCreate) {
            $validation = $this->addressValidationFactory->create($context);
        } else {
            $validation = $this->addressValidationFactory->update($context);
        }

        $requestSelectedBusiness = $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS
            && (bool) $this->systemConfigService->get('core.loginRegistration.showAccountTypeSelection', $context->getSalesChannelId());

        if ($namesAreOptional || $requestSelectedBusiness) {
            $validation->add('company', CompanyAccountNameFields::companyNotBlank());
        }

        if ($namesAreOptional) {
            CompanyAccountNameFields::makeOptional($validation);
        }

        $validation->set('zipcode', new CustomerZipCode(countryId: $data->get('countryId')));

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    private function getDefaultSalutationId(SalesChannelContext $context): ?string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('salutationKey', SalutationDefinition::NOT_SPECIFIED));

        return $this->salutationRepository->searchIds($criteria, $context->getContext())->firstId();
    }
}
