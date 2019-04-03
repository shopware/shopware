<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Shipping\Exception\ShippingMethodNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\CheckoutContextPersister;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

class StorefrontCheckoutContextController extends AbstractController
{
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
     * @var CheckoutContextPersister
     */
    protected $contextPersister;

    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $customerAddressRepository,
        CheckoutContextPersister $contextPersister,
        Serializer $serializer
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->contextPersister = $contextPersister;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/storefront-api/v{version}/context", name="storefront-api.context.update", methods={"PATCH"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     */
    public function update(Request $request, CheckoutContext $context): JsonResponse
    {
        $payload = $request->request->all();

        $update = [];
        if (array_key_exists('shippingMethodId', $payload)) {
            $update['shippingMethodId'] = $this->validateShippingMethodId($payload['shippingMethodId'], $context);
        }
        if (array_key_exists('paymentMethodId', $payload)) {
            $update['paymentMethodId'] = $this->validatePaymentMethodId($payload['paymentMethodId'], $context);
        }
        if (array_key_exists('billingAddressId', $payload)) {
            $update['billingAddressId'] = $this->validateAddressId($payload['billingAddressId'], $context);
        }
        if (array_key_exists('shippingAddressId', $payload)) {
            $update['shippingAddressId'] = $this->validateAddressId($payload['shippingAddressId'], $context);
        }

        $this->contextPersister->save($context->getToken(), $update);

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken(),
        ]);
    }

    private function validatePaymentMethodId(string $paymentMethodId, CheckoutContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('payment_method.id', $paymentMethodId));

        $valid = $this->paymentMethodRepository->searchIds($criteria, $context->getContext());
        if (!\in_array($paymentMethodId, $valid->getIds(), true)) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $paymentMethodId;
    }

    private function validateShippingMethodId(string $shippingMethodId, CheckoutContext $context): string
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
    private function validateAddressId(string $addressId, CheckoutContext $context): string
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
}
