<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Shipping\Exception\ShippingMethodNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\Exception\CountryNotFoundException;
use Shopware\Core\System\Country\Exception\CountryStateNotFoundException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

class SalesChannelContextController extends AbstractController
{
    private const SHIPPING_METHOD_ID = SalesChannelContextService::SHIPPING_METHOD_ID;
    private const PAYMENT_METHOD_ID = SalesChannelContextService::PAYMENT_METHOD_ID;
    private const BILLING_ADDRESS_ID = SalesChannelContextService::BILLING_ADDRESS_ID;
    private const SHIPPING_ADDRESS_ID = SalesChannelContextService::SHIPPING_ADDRESS_ID;
    private const COUNTRY_ID = SalesChannelContextService::COUNTRY_ID;
    private const STATE_ID = SalesChannelContextService::STATE_ID;

    /**
     * @var EntityRepositoryInterface
     */
    protected $paymentMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $shippingMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $customerAddressRepository;

    /**
     * @var SalesChannelContextPersister
     */
    protected $contextPersister;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var EntityRepositoryInterface
     */
    protected $countryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $countryStateRepository;

    public function __construct(
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $customerAddressRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $countryStateRepository,
        SalesChannelContextPersister $contextPersister,
        Serializer $serializer
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->contextPersister = $contextPersister;
        $this->serializer = $serializer;
        $this->countryRepository = $countryRepository;
        $this->countryStateRepository = $countryStateRepository;
    }

    /**
     * @Route("/sales-channel-api/v{version}/context", name="sales-channel-api.context.update", methods={"PATCH"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     */
    public function update(Request $request, SalesChannelContext $context): JsonResponse
    {
        $payload = $request->request->all();

        $update = [];
        if (array_key_exists(self::SHIPPING_METHOD_ID, $payload)) {
            $update[self::SHIPPING_METHOD_ID] = $this->validateShippingMethodId($payload[self::SHIPPING_METHOD_ID], $context);
        }
        if (array_key_exists(self::PAYMENT_METHOD_ID, $payload)) {
            $update[self::PAYMENT_METHOD_ID] = $this->validatePaymentMethodId($payload[self::PAYMENT_METHOD_ID], $context);
        }
        if (array_key_exists(self::BILLING_ADDRESS_ID, $payload)) {
            $update[self::BILLING_ADDRESS_ID] = $this->validateAddressId($payload[self::BILLING_ADDRESS_ID], $context);
        }
        if (array_key_exists(self::SHIPPING_ADDRESS_ID, $payload)) {
            $update[self::SHIPPING_ADDRESS_ID] = $this->validateAddressId($payload[self::SHIPPING_ADDRESS_ID], $context);
        }
        if (array_key_exists(self::COUNTRY_ID, $payload)) {
            $update[self::COUNTRY_ID] = $this->validateCountryId($payload[self::COUNTRY_ID], $context);
        }
        if (array_key_exists(self::STATE_ID, $payload)) {
            $update[self::STATE_ID] = $this->validateCountryStateId($payload[self::STATE_ID], $context);
        }

        $this->contextPersister->save($context->getToken(), $update);

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken(),
        ]);
    }

    private function validatePaymentMethodId(string $paymentMethodId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('payment_method.id', $paymentMethodId));

        $valid = $this->paymentMethodRepository->searchIds($criteria, $context->getContext());
        if (!\in_array($paymentMethodId, $valid->getIds(), true)) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $paymentMethodId;
    }

    private function validateShippingMethodId(string $shippingMethodId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shipping_method.id', $shippingMethodId));

        $valid = $this->shippingMethodRepository->searchIds($criteria, $context->getContext());
        if (!\in_array($shippingMethodId, $valid->getIds(), true)) {
            throw new ShippingMethodNotFoundException($shippingMethodId);
        }

        return $shippingMethodId;
    }

    /**
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     */
    private function validateAddressId(string $addressId, SalesChannelContext $context): string
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $addresses = $this->customerAddressRepository->search(new Criteria([$addressId]), $context->getContext());
        /** @var CustomerAddressEntity|null $address */
        $address = $addresses->get($addressId);

        if (!$address) {
            throw new AddressNotFoundException($addressId);
        }

        if ($address->getCustomerId() !== $context->getCustomer()->getId()) {
            throw new AddressNotFoundException($addressId);
        }

        return $addressId;
    }

    private function validateCountryId(string $countryId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('country.id', $countryId));

        $valid = $this->countryRepository->searchIds($criteria, $context->getContext());
        if (!\in_array($countryId, $valid->getIds(), true)) {
            throw new CountryNotFoundException($countryId);
        }

        return $countryId;
    }

    private function validateCountryStateId(string $stateId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('country_state.id', $stateId));

        $valid = $this->countryStateRepository->searchIds($criteria, $context->getContext());
        if (!\in_array($stateId, $valid->getIds(), true)) {
            throw new CountryStateNotFoundException($stateId);
        }

        return $stateId;
    }
}
