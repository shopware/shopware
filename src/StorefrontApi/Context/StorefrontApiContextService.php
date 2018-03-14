<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Context;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Context\Service\ContextFactory;
use Shopware\Context\Service\ContextRuleLoader;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\ShopScope;
use Shopware\Context\Struct\StorefrontContext;

class StorefrontApiContextService
{
    /**
     * @var ContextFactory
     */
    private $factory;

    /**
     * @var ContextRuleLoader
     */
    private $contextRuleLoader;

    /**
     * @var ShopRepository
     */
    private $applicationRepository;

    /**
     * @var StorefrontApiContextPersister
     */
    private $contextParameterPersister;

    public function __construct(
        ContextFactory $factory,
        ContextRuleLoader $contextRuleLoader,
        ShopRepository $applicationRepository,
        StorefrontApiContextPersister $contextParameterPersister
    ) {
        $this->factory = $factory;
        $this->contextRuleLoader = $contextRuleLoader;
        $this->applicationRepository = $applicationRepository;
        $this->contextParameterPersister = $contextParameterPersister;
    }

    public function load(string $applicationId, ?string $contextToken = null): StorefrontContext
    {
        $application = $this->applicationRepository->readBasic([$applicationId], ShopContext::createDefaultContext());
        $application = $application->get($applicationId);

        if (!$contextToken) {
            $contextToken = Uuid::uuid4()->toString();
        }

        $contextParameter = $this->contextParameterPersister->load((string) $contextToken);

        $shopScope = new ShopScope(
            $applicationId,
            $this->getCurrencyId($contextParameter, $application)
        );

        $customerScope = new CustomerScope(
            $this->getCustomerId($contextParameter),
            $this->getCustomerGroupId($contextParameter, $application),
            $this->getBillingAddressId($contextParameter),
            $this->getShippingAddressId($contextParameter)
        );

        $checkoutScope = new CheckoutScope(
            $this->getPaymentMethodId($contextParameter, $application),
            $this->getShippingMethodId($contextParameter, $application),
            $this->getCountryId($contextParameter, $application),
            $this->getStateId($contextParameter, $application),
            $this->getCartToken($contextParameter)
        );

        $context = $this->factory->create($shopScope, $customerScope, $checkoutScope);

        $rules = $this->contextRuleLoader->loadMatchingRules($context, $checkoutScope->getCartToken());

        $context = new StorefrontApiContext(
            $contextToken,
            $context->getShop(),
            $context->getCurrency(),
            $context->getCurrentCustomerGroup(),
            $context->getFallbackCustomerGroup(),
            $context->getTaxRules(),
            $context->getPaymentMethod(),
            $context->getShippingMethod(),
            $context->getShippingLocation(),
            $checkoutScope->getCartToken(),
            $context->getCustomer(),
            $rules->getIds()
        );

        $context->lockRules();

        return $context;
    }

    private function getCurrencyId(array $contextParameter, ShopBasicStruct $application): ?string
    {
        if (isset($contextParameter['currencyId'])) {
            return $contextParameter['currencyId'];
        }

        return $application->getCurrencyId();
    }

    private function getCustomerId(array $contextParameter): ?string
    {
        if (isset($contextParameter['customerId'])) {
            return $contextParameter['customerId'];
        }

        return null;
    }

    private function getCustomerGroupId(array $contextParameter, ShopBasicStruct $application): string
    {
        if (isset($contextParameter['customerGroupId'])) {
            return $contextParameter['customerGroupId'];
        }

        return $application->getCustomerGroupId();
    }

    private function getBillingAddressId(array $contextParameter): ?string
    {
        if (isset($contextParameter['billingAddressId'])) {
            return $contextParameter['billingAddressId'];
        }

        return null;
    }

    private function getShippingAddressId(array $contextParameter): ?string
    {
        if (isset($contextParameter['shippingAddressId'])) {
            return $contextParameter['shippingAddressId'];
        }

        return null;
    }

    private function getPaymentMethodId(array $contextParameter, ShopBasicStruct $application): string
    {
        if (isset($contextParameter['paymentMethodId'])) {
            return $contextParameter['paymentMethodId'];
        }

        return $application->getPaymentMethodId();
    }

    private function getShippingMethodId(array $contextParameter, ShopBasicStruct $application): string
    {
        if (isset($contextParameter['shippingMethodId'])) {
            return $contextParameter['shippingMethodId'];
        }

        return $application->getShippingMethodId();
    }

    private function getCountryId(array $contextParameter, ShopBasicStruct $application): string
    {
        if (isset($contextParameter['countryId'])) {
            return $contextParameter['countryId'];
        }

        return $application->getCountryId();
    }

    private function getStateId(array $contextParameter, ShopBasicStruct $application): ?string
    {
        if (isset($contextParameter['stateId'])) {
            return $contextParameter['stateId'];
        }

        return null;
    }

    private function getCartToken(array $contextParameter): ?string
    {
        if (isset($contextParameter['cartToken'])) {
            return $contextParameter['cartToken'];
        }

        return null;
    }
}
