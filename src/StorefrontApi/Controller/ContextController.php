<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Shopware\Api\Customer\Repository\CustomerAddressRepository;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Payment\Repository\PaymentMethodRepository;
use Shopware\Api\Shipping\Repository\ShippingMethodRepository;
use Shopware\CartBridge\Exception\NotLoggedInCustomerException;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextValueResolver;
use Shopware\StorefrontApi\Exception\AddressNotFoundHttpException;
use Shopware\StorefrontApi\Exception\PaymentMethodNotFoundHttpException;
use Shopware\StorefrontApi\Exception\ShippingMethodNotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

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

    public function __construct(
        PaymentMethodRepository $paymentMethodRepository,
        ShippingMethodRepository $shippingMethodRepository,
        CustomerAddressRepository $customerAddressRepository,
        StorefrontContextPersister $contextPersister
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->contextPersister = $contextPersister;
    }

    public function switchPaymentMethodAction(string $paymentMethodId, StorefrontContext $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('payment_method.id', $paymentMethodId));

        $valid = $this->paymentMethodRepository->searchIds($criteria, $context);
        if (!in_array($paymentMethodId, $valid->getIds())) {
            throw new PaymentMethodNotFoundHttpException($paymentMethodId);
        }

        $this->contextPersister->save($context->getToken(), [
            'paymentMethodId' => $paymentMethodId,
        ]);

        return new JsonResponse([
            StorefrontContextValueResolver::CONTEXT_TOKEN_KEY => $context->getToken(),
        ]);
    }

    public function switchShippingMethodAction(string $shippingMethodId, StorefrontContext $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('shipping_method.id', $shippingMethodId));

        $valid = $this->shippingMethodRepository->searchIds($criteria, $context);
        if (!in_array($shippingMethodId, $valid->getIds())) {
            throw new ShippingMethodNotFoundHttpException($shippingMethodId);
        }

        $this->contextPersister->save($context->getToken(), [
            'shippingMethodId' => $shippingMethodId,
        ]);

        return new JsonResponse([
            StorefrontContextValueResolver::CONTEXT_TOKEN_KEY => $context->getToken(),
        ]);
    }

    public function switchBillingAddressAction(string $addressId, StorefrontContext $context)
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

        $this->contextPersister->save($context->getToken(), [
            'billingAddressId' => $addressId,
        ]);

        return new JsonResponse([
            StorefrontContextValueResolver::CONTEXT_TOKEN_KEY => $context->getToken(),
        ]);
    }

    public function switchShippingAddressAction(string $addressId, StorefrontContext $context)
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

        $this->contextPersister->save($context->getToken(), [
            'shippingAddressId' => $addressId,
        ]);

        return new JsonResponse([
            StorefrontContextValueResolver::CONTEXT_TOKEN_KEY => $context->getToken(),
        ]);
    }
}
