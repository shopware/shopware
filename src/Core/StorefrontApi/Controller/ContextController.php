<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Checkout\Customer\Repository\CustomerAddressRepository;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Checkout\Payment\Repository\PaymentMethodRepository;
use Shopware\Checkout\Shipping\Repository\ShippingMethodRepository;
use Shopware\Checkout\CartBridge\Exception\NotLoggedInCustomerException;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Application\ApplicationResolver;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Exception\AddressNotFoundHttpException;
use Shopware\StorefrontApi\Exception\PaymentMethodNotFoundHttpException;
use Shopware\StorefrontApi\Exception\ShippingMethodNotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Serializer;

class ContextController extends Controller
{
    /**
     * @var PaymentMethodRepository
     */
    protected $paymentMethodRepository;

    /**
     * @var ShippingMethodRepository
     */
    protected $shippingMethodRepository;

    /**
     * @var CustomerAddressRepository
     */
    protected $customerAddressRepository;

    /**
     * @var \Shopware\StorefrontApi\Context\StorefrontContextPersister
     */
    protected $contextPersister;

    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        PaymentMethodRepository $paymentMethodRepository,
        ShippingMethodRepository $shippingMethodRepository,
        CustomerAddressRepository $customerAddressRepository,
        StorefrontContextPersister $contextPersister,
        Serializer $serializer
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->contextPersister = $contextPersister;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/storefront-api/context/", name="storefront.api.context.update")
     * @Method({"PUT"})
     */
    public function update(StorefrontContext $context): JsonResponse
    {
        $payload = $this->getPayload();

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
            ApplicationResolver::CONTEXT_HEADER => $context->getToken(),
        ]);
    }

    private function validatePaymentMethodId(string $paymentMethodId, StorefrontContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('payment_method.id', $paymentMethodId));

        $valid = $this->paymentMethodRepository->searchIds($criteria, $context);
        if (!in_array($paymentMethodId, $valid->getIds(), true)) {
            throw new PaymentMethodNotFoundHttpException($paymentMethodId);
        }

        return $paymentMethodId;
    }

    private function validateShippingMethodId(string $shippingMethodId, StorefrontContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('shipping_method.id', $shippingMethodId));

        $valid = $this->shippingMethodRepository->searchIds($criteria, $context);
        if (!in_array($shippingMethodId, $valid->getIds(), true)) {
            throw new ShippingMethodNotFoundHttpException($shippingMethodId);
        }

        return $shippingMethodId;
    }

    private function validateAddressId(string $addressId, StorefrontContext $context): string
    {
        if (!$context->getCustomer()) {
            throw new NotLoggedInCustomerException();
        }

        $addresses = $this->customerAddressRepository->readBasic([$addressId], $context);
        $address = $addresses->get($addressId);

        if (!$address) {
            throw new AddressNotFoundHttpException($addressId);
        }

        if ($address->getCustomerId() !== $context->getCustomer()->getId()) {
            throw new AddressNotFoundHttpException($addressId);
        }

        return $addressId;
    }

    private function getPayload(Request $request): array
    {
        if (empty($request->getContent())) {
            return [];
        }

        return $this->serializer->decode($request->getContent(), 'json');
    }
}
