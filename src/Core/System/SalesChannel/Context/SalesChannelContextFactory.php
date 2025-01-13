<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\AbstractTaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Shopware\Core\System\SalesChannel\BaseContext;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextPermissionsChangedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxRuleType\TaxRuleTypeFilterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('discovery')]
class SalesChannelContextFactory extends AbstractSalesChannelContextFactory
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     * @param EntityRepository<CustomerAddressCollection> $addressRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param iterable<TaxRuleTypeFilterInterface> $taxRuleTypeFilter
     * @param EntityRepository<CurrencyCountryRoundingCollection> $currencyCountryRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $addressRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly AbstractTaxDetector $taxDetector,
        private readonly iterable $taxRuleTypeFilter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $currencyCountryRepository,
        private readonly AbstractBaseContextFactory $baseContextFactory
    ) {
    }

    public function getDecorated(): AbstractSalesChannelContextFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        // we split the context generation to allow caching of the base context
        $base = $this->baseContextFactory->create($salesChannelId, $options);

        // customer
        $customer = null;
        if (\array_key_exists(SalesChannelContextService::CUSTOMER_ID, $options) && $options[SalesChannelContextService::CUSTOMER_ID] !== null) {
            // load logged in customer and set active addresses
            $customer = $this->loadCustomer($options, $base->getContext());
        }

        $shippingLocation = $base->getShippingLocation();
        if ($customer) {
            $activeShippingAddress = $customer->getActiveShippingAddress();
            \assert($activeShippingAddress !== null);
            $shippingLocation = ShippingLocation::createFromAddress($activeShippingAddress);
        }

        $customerGroup = $base->getCurrentCustomerGroup();

        if ($customer) {
            $criteria = new Criteria([$customer->getGroupId()]);
            $criteria->setTitle('context-factory::customer-group');
            $customerGroup = $this->customerGroupRepository->search($criteria, $base->getContext())->getEntities()->first() ?? $customerGroup;
        }

        // loads tax rules based on active customer and delivery address
        $taxRules = $this->getTaxRules($base, $customer, $shippingLocation);

        // detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $base, $customer);

        [$itemRounding, $totalRounding] = $this->getCashRounding($base, $shippingLocation);

        $context = new Context(
            $base->getContext()->getSource(),
            [],
            $base->getCurrencyId(),
            $base->getContext()->getLanguageIdChain(),
            $base->getContext()->getVersionId(),
            $base->getCurrency()->getFactor(),
            true,
            CartPrice::TAX_STATE_GROSS,
            $itemRounding
        );

        $salesChannelContext = new SalesChannelContext(
            $context,
            $token,
            $options[SalesChannelContextService::DOMAIN_ID] ?? null,
            $base->getSalesChannel(),
            $base->getCurrency(),
            $customerGroup,
            $taxRules,
            $payment,
            $base->getShippingMethod(),
            $shippingLocation,
            $customer,
            $itemRounding,
            $totalRounding,
            [],
            $base->getLanguageInfo(),
        );

        if (\array_key_exists(SalesChannelContextService::PERMISSIONS, $options)) {
            $salesChannelContext->setPermissions($options[SalesChannelContextService::PERMISSIONS]);

            $event = new SalesChannelContextPermissionsChangedEvent($salesChannelContext, $options[SalesChannelContextService::PERMISSIONS]);
            $this->eventDispatcher->dispatch($event);

            $salesChannelContext->lockPermissions();
        }

        if (\array_key_exists(SalesChannelContextService::IMITATING_USER_ID, $options)) {
            $salesChannelContext->setImitatingUserId($options[SalesChannelContextService::IMITATING_USER_ID]);
        }

        $salesChannelContext->setTaxState($this->taxDetector->getTaxState($salesChannelContext));

        return $salesChannelContext;
    }

    private function getTaxRules(BaseContext $context, ?CustomerEntity $customer, ShippingLocation $shippingLocation): TaxCollection
    {
        $taxes = $context->getTaxRules()->getElements();

        foreach ($taxes as $tax) {
            $taxRules = $tax->getRules();
            if ($taxRules === null) {
                continue;
            }

            $taxRules = $taxRules->filter(function (TaxRuleEntity $taxRule) use ($customer, $shippingLocation) {
                foreach ($this->taxRuleTypeFilter as $ruleTypeFilter) {
                    if ($ruleTypeFilter->match($taxRule, $customer, $shippingLocation)) {
                        return true;
                    }
                }

                return false;
            });

            $matchingRules = new TaxRuleCollection();
            $taxRule = $taxRules->highestTypePosition();

            if (!$taxRule) {
                $tax->setRules($matchingRules);

                continue;
            }

            $taxRules = $taxRules->filterByTypePosition($taxRule->getType()->getPosition());
            $taxRule = $taxRules->latestActivationDate();

            if ($taxRule) {
                $matchingRules->add($taxRule);
            }
            $tax->setRules($matchingRules);
        }

        return new TaxCollection($taxes);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param array<string, mixed> $options
     */
    private function getPaymentMethod(array $options, BaseContext $context, ?CustomerEntity $customer): PaymentMethodEntity
    {
        if ($customer === null || isset($options[SalesChannelContextService::PAYMENT_METHOD_ID])) {
            return $context->getPaymentMethod();
        }

        $id = $customer->getLastPaymentMethodId();

        if ($id === null) {
            Feature::callSilentIfInactive('v6.7.0.0', static function () use ($customer, &$id): void {
                $id = $customer->getDefaultPaymentMethodId();
            });
        }

        if ($id === null || $id === $context->getPaymentMethod()->getId()) {
            return $context->getPaymentMethod();
        }

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('media');
        $criteria->addAssociation('appPaymentMethod');
        $criteria->setTitle('context-factory::payment-method');
        $criteria->addFilter(new EqualsFilter('active', 1));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));

        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context->getContext())->getEntities()->get($id);
        if (!$paymentMethod) {
            return $context->getPaymentMethod();
        }

        return $paymentMethod;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function loadCustomer(array $options, Context $context): ?CustomerEntity
    {
        $addressIds = [];
        $customerId = $options[SalesChannelContextService::CUSTOMER_ID];

        $criteria = new Criteria([$customerId]);
        $criteria->setTitle('context-factory::customer');
        $criteria->addAssociation('salutation');

        if (!Feature::isActive('v6.7.0.0')) {
            $criteria->addAssociation('defaultPaymentMethod');
        }

        $source = $context->getSource();
        \assert($source instanceof SalesChannelApiSource);

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('customer.boundSalesChannelId', null),
            new EqualsFilter('customer.boundSalesChannelId', $source->getSalesChannelId()),
        ]));

        $customer = $this->customerRepository->search($criteria, $context)->getEntities()->get($customerId);
        if (!$customer) {
            return null;
        }

        $activeBillingAddressId = $options[SalesChannelContextService::BILLING_ADDRESS_ID] ?? $customer->getDefaultBillingAddressId();
        $activeShippingAddressId = $options[SalesChannelContextService::SHIPPING_ADDRESS_ID] ?? $customer->getDefaultShippingAddressId();

        $addressIds[] = $activeBillingAddressId;
        $addressIds[] = $activeShippingAddressId;
        $addressIds[] = $customer->getDefaultBillingAddressId();
        $addressIds[] = $customer->getDefaultShippingAddressId();

        $criteria = new Criteria(\array_unique($addressIds));
        $criteria->setTitle('context-factory::addresses');
        $criteria->addAssociation('salutation');
        $criteria->addAssociation('country');
        $criteria->addAssociation('countryState');

        $addresses = $this->addressRepository->search($criteria, $context)->getEntities();

        $activeBillingAddress = $addresses->get($activeBillingAddressId) ?? $addresses->get($customer->getDefaultBillingAddressId());
        \assert($activeBillingAddress !== null);
        $customer->setActiveBillingAddress($activeBillingAddress);
        $activeShippingAddress = $addresses->get($activeShippingAddressId) ?? $addresses->get($customer->getDefaultShippingAddressId());
        \assert($activeShippingAddress !== null);
        $customer->setActiveShippingAddress($activeShippingAddress);
        $defaultBillingAddress = $addresses->get($customer->getDefaultBillingAddressId());
        \assert($defaultBillingAddress !== null);
        $customer->setDefaultBillingAddress($defaultBillingAddress);
        $defaultShippingAddress = $addresses->get($customer->getDefaultShippingAddressId());
        \assert($defaultShippingAddress !== null);
        $customer->setDefaultShippingAddress($defaultShippingAddress);

        return $customer;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array{CashRoundingConfig, CashRoundingConfig}
     */
    private function getCashRounding(BaseContext $context, ShippingLocation $shippingLocation): array
    {
        if ($context->getShippingLocation()->getCountry()->getId() === $shippingLocation->getCountry()->getId()) {
            return [$context->getItemRounding(), $context->getTotalRounding()];
        }

        $criteria = new Criteria();
        $criteria->setTitle('context-factory::cash-rounding');
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('currencyId', $context->getCurrencyId()));
        $criteria->addFilter(new EqualsFilter('countryId', $shippingLocation->getCountry()->getId()));

        $countryConfig = $this->currencyCountryRepository
            ->search($criteria, $context->getContext())
            ->getEntities()
            ->first();

        if ($countryConfig) {
            return [$countryConfig->getItemRounding(), $countryConfig->getTotalRounding()];
        }

        return [$context->getCurrency()->getItemRounding(), $context->getCurrency()->getTotalRounding()];
    }
}
