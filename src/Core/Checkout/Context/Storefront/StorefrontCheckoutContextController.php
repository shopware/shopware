<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context\Storefront;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Payment\Exception\PaymentMethodNotFoundException;
use Shopware\Core\Checkout\Shipping\Exception\ShippingMethodNotFoundException;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Exception\AddressNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

class StorefrontCheckoutContextController extends Controller
{
    /**
     * @var RepositoryInterface
     */
    protected $paymentMethodRepository;

    /**
     * @var RepositoryInterface
     */
    protected $shippingMethodRepository;

    /**
     * @var RepositoryInterface
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
        RepositoryInterface $paymentMethodRepository,
        RepositoryInterface $shippingMethodRepository,
        RepositoryInterface $customerAddressRepository,
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
     * @Route("/storefront-api/context", name="storefront.api.context.update", methods={"PUT"})
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

        $this->contextPersister->save($context->getToken(), $update, $context->getTenantId());

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken(),
        ]);
    }

    private function validatePaymentMethodId(string $paymentMethodId, CheckoutContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('payment_method.id', $paymentMethodId));

        $valid = $this->paymentMethodRepository->searchIds($criteria, $context->getContext());
        if (!in_array($paymentMethodId, $valid->getIds(), true)) {
            throw new PaymentMethodNotFoundException($paymentMethodId);
        }

        return $paymentMethodId;
    }

    private function validateShippingMethodId(string $shippingMethodId, CheckoutContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('shipping_method.id', $shippingMethodId));

        $valid = $this->shippingMethodRepository->searchIds($criteria, $context->getContext());
        if (!in_array($shippingMethodId, $valid->getIds(), true)) {
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

        $addresses = $this->customerAddressRepository->read(new ReadCriteria([$addressId]), $context->getContext());
        /** @var CustomerAddressStruct $address */
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
