<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class UpsertAddressRoute extends AbstractUpsertAddressRoute
{
    use CustomerAddressValidationTrait;

    /**
     * @var EntityRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DataValidationFactoryInterface
     */
    private $addressValidationFactory;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $addressRepository,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        DataValidationFactoryInterface $addressValidationFactory,
        SystemConfigService $systemConfigService
    ) {
        $this->addressRepository = $addressRepository;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
        $this->addressValidationFactory = $addressValidationFactory;
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractUpsertAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/address",
     *      description="Create a new address",
     *      operationId="createCustomerAddress",
     *      tags={"Store API", "Account", "Address"},
     *      @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/customer_address_flat")),
     *      @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(ref="#/components/schemas/customer_address_flat")
     *     )
     * )
     * @OA\Patch(
     *      path="/account/address/{addressId}",
     *      description="Update address",
     *      operationId="updateCustomerAddress",
     *      tags={"Store API", "Account", "Address"},
     *      @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/customer_address_flat")),
     *      @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(ref="#/components/schemas/customer_address_flat")
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/address", name="store-api.account.address.create", methods={"POST"}, defaults={"addressId": null})
     * @Route(path="/store-api/v{version}/account/address/{addressId}", name="store-api.account.address.update", methods={"PATCH"})
     */
    public function upsert(?string $addressId, RequestDataBag $data, SalesChannelContext $context): UpsertAddressRouteResponse
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        if (!$addressId) {
            $isCreate = true;
            $addressId = Uuid::randomHex();
        } else {
            $this->validateAddress($addressId, $context);
            $isCreate = false;
        }

        $accountType = $data->get('accountType', CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $definition = $this->getValidationDefinition($accountType, $isCreate, $context);
        $this->validator->validate(array_merge(['id' => $addressId], $data->all()), $definition);

        $addressData = [
            'salutationId' => $data->get('salutationId'),
            'firstName' => $data->get('firstName'),
            'lastName' => $data->get('lastName'),
            'street' => $data->get('street'),
            'city' => $data->get('city'),
            'zipcode' => $data->get('zipcode'),
            'countryId' => $data->get('countryId'),
            'countryStateId' => $data->get('countryStateId') ? $data->get('countryStateId') : null,
            'company' => $data->get('company'),
            'department' => $data->get('department'),
            'title' => $data->get('title'),
            'vatId' => $data->get('vatId'),
            'phoneNumber' => $data->get('phoneNumber'),
            'additionalAddressLine1' => $data->get('additionalAddressLine1'),
            'additionalAddressLine2' => $data->get('additionalAddressLine2'),
        ];

        $mappingEvent = new DataMappingEvent($data, $addressData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_ADDRESS_CREATE);

        $addressData = $mappingEvent->getOutput();
        $addressData['id'] = $addressId;
        $addressData['customerId'] = $context->getCustomer()->getId();

        $this->addressRepository->upsert([$addressData], $context->getContext());

        $criteria = new Criteria([$addressId]);

        /** @var CustomerAddressEntity $address */
        $address = $this->addressRepository->search($criteria, $context->getContext())->first();

        return new UpsertAddressRouteResponse($address);
    }

    private function getValidationDefinition(string $accountType, bool $isCreate, SalesChannelContext $context): DataValidationDefinition
    {
        if ($isCreate) {
            $validation = $this->addressValidationFactory->create($context);
        } else {
            $validation = $this->addressValidationFactory->update($context);
        }

        if ($accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS && $this->systemConfigService->get('core.loginRegistration.showAccountTypeSelection')) {
            $validation->add('company', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($validation, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}
