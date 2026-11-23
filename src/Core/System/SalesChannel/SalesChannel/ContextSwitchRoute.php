<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\ProspectiveAddress;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\Constraint\NoHtml;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\SalesChannel\Event\SwitchContextEvent;
use Shopware\Core\System\SalesChannel\Extension\ContextSwitchRouteExtension;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ContextSwitchRoute extends AbstractContextSwitchRoute
{
    private const SHIPPING_METHOD_ID = SalesChannelContextService::SHIPPING_METHOD_ID;
    private const PAYMENT_METHOD_ID = SalesChannelContextService::PAYMENT_METHOD_ID;
    private const BILLING_ADDRESS_ID = SalesChannelContextService::BILLING_ADDRESS_ID;
    private const SHIPPING_ADDRESS_ID = SalesChannelContextService::SHIPPING_ADDRESS_ID;
    private const COUNTRY_ID = SalesChannelContextService::COUNTRY_ID;
    private const STATE_ID = SalesChannelContextService::COUNTRY_STATE_ID;
    private const CURRENCY_ID = SalesChannelContextService::CURRENCY_ID;
    private const LANGUAGE_ID = SalesChannelContextService::LANGUAGE_ID;
    private const SHIPPING_ADDRESS = 'shippingAddress';
    private const BILLING_ADDRESS = 'billingAddress';

    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidator $validator,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractContextSwitchRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context', name: 'store-api.switch-context', methods: ['PATCH'])]
    public function switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        return $this->extensions->publish(
            name: ContextSwitchRouteExtension::NAME,
            extension: new ContextSwitchRouteExtension($data, $context),
            function: $this->_switchContext(...),
        );
    }

    private function _switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        $definition = new DataValidationDefinition('context_switch');

        $parameters = $data->only(
            self::SHIPPING_METHOD_ID,
            self::PAYMENT_METHOD_ID,
            self::BILLING_ADDRESS_ID,
            self::SHIPPING_ADDRESS_ID,
            self::COUNTRY_ID,
            self::STATE_ID,
            self::CURRENCY_ID,
            self::LANGUAGE_ID
        );

        // pre validate to ensure correct data type. Existence of entities is checked later
        $definition
            ->add(self::LANGUAGE_ID, new Type('string'))
            ->add(self::CURRENCY_ID, new Type('string'))
            ->add(self::SHIPPING_METHOD_ID, new Type('string'))
            ->add(self::PAYMENT_METHOD_ID, new Type('string'))
            ->add(self::BILLING_ADDRESS_ID, new Type('string'))
            ->add(self::SHIPPING_ADDRESS_ID, new Type('string'))
            ->add(self::COUNTRY_ID, new Type('string'))
            ->add(self::STATE_ID, new Type('string'))
        ;

        // the address of a visitor without a customer, stored as the `ProspectiveCustomer`
        $addressDefinitions = [];
        foreach ([self::SHIPPING_ADDRESS, self::BILLING_ADDRESS] as $field) {
            if (!$data->has($field)) {
                continue;
            }

            if ($context->getCustomer() !== null) {
                throw SalesChannelException::prospectiveAddressWithCustomer($field);
            }

            $address = $data->get($field);

            // the context country follows the shipping address, a second country in the same request would be ignored
            if ($field === self::SHIPPING_ADDRESS && $address instanceof DataBag) {
                foreach ([self::COUNTRY_ID, self::STATE_ID] as $countryField) {
                    if ($data->has($countryField)) {
                        throw SalesChannelException::prospectiveAddressWithCountry($countryField);
                    }
                }
            }

            $parameters[$field] = $address instanceof DataBag ? $address->only(...ProspectiveAddress::FIELDS) : $address;
            $definition->add($field, new Type('array'));

            if ($address instanceof DataBag) {
                $addressDefinitions[$field] = $this->createAddressDefinition();
                $definition->addSub($field, $addressDefinitions[$field]);
            }
        }

        // a billing address alone does not locate the visitor
        if ($data->get(self::BILLING_ADDRESS) instanceof DataBag && !$data->has(self::SHIPPING_ADDRESS) && $context->getProspectiveCustomer() === null) {
            $definition->add(self::SHIPPING_ADDRESS, new NotNull());
        }

        $event = new SwitchContextEvent($data, $context, $definition, $parameters);
        $this->eventDispatcher->dispatch($event, SwitchContextEvent::CONSISTENT_CHECK);
        $parameters = $event->getParameters();

        $this->validator->validate($parameters, $definition);

        $addressCriteria = new Criteria();
        if ($context->getCustomer()) {
            $addressCriteria->addFilter(new EqualsFilter('customer_address.customerId', $context->getCustomerId()));
        } else {
            // do not allow setting address ids if the customer is not logged in
            if (isset($parameters[self::SHIPPING_ADDRESS_ID]) && $parameters[self::SHIPPING_ADDRESS_ID] !== '') {
                throw SalesChannelException::customerNotLoggedIn();
            }

            if (isset($parameters[self::BILLING_ADDRESS_ID]) && $parameters[self::BILLING_ADDRESS_ID] !== '') {
                throw SalesChannelException::customerNotLoggedIn();
            }
        }

        $salesChannelId = $context->getSalesChannelId();
        $frameworkContext = $context->getContext();

        $currencyCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('currency.salesChannels.id', $salesChannelId));

        $languageCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('language.salesChannels.id', $salesChannelId));

        $paymentMethodCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('payment_method.salesChannels.id', $salesChannelId));

        $shippingMethodCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('shipping_method.salesChannels.id', $salesChannelId));

        $definition
            ->add(self::LANGUAGE_ID, new EntityExists(entity: 'language', context: $frameworkContext, criteria: $languageCriteria))
            ->add(self::CURRENCY_ID, new EntityExists(entity: 'currency', context: $frameworkContext, criteria: $currencyCriteria))
            ->add(self::SHIPPING_METHOD_ID, new EntityExists(entity: 'shipping_method', context: $frameworkContext, criteria: $shippingMethodCriteria))
            ->add(self::PAYMENT_METHOD_ID, new EntityExists(entity: 'payment_method', context: $frameworkContext, criteria: $paymentMethodCriteria))
            ->add(self::BILLING_ADDRESS_ID, new EntityExists(entity: 'customer_address', context: $frameworkContext, criteria: $addressCriteria))
            ->add(self::SHIPPING_ADDRESS_ID, new EntityExists(entity: 'customer_address', context: $frameworkContext, criteria: $addressCriteria))
            ->add(self::COUNTRY_ID, new EntityExists(entity: 'country', context: $frameworkContext))
            ->add(self::STATE_ID, new EntityExists(entity: 'country_state', context: $frameworkContext))
        ;

        foreach ($addressDefinitions as $field => $addressDefinition) {
            $stateCriteria = (new Criteria())
                ->addFilter(new EqualsFilter('country_state.countryId', ProspectiveAddress::fromPayload($parameters[$field] ?? null)?->countryId));

            $addressDefinition
                ->add('countryId', new EntityExists(entity: 'country', context: $frameworkContext))
                ->add('countryStateId', new EntityExists(entity: 'country_state', context: $frameworkContext, criteria: $stateCriteria));
        }

        $event = new SwitchContextEvent($data, $context, $definition, $parameters);
        $this->eventDispatcher->dispatch($event, SwitchContextEvent::DATABASE_CHECK);
        $parameters = $event->getParameters();

        $this->validator->validate($parameters, $definition);

        if (\array_key_exists(self::SHIPPING_ADDRESS, $parameters) || \array_key_exists(self::BILLING_ADDRESS, $parameters)) {
            $this->validateZipCodes($parameters);
            $parameters = $this->toProspectiveCustomer($parameters);
        }

        if (!$data->has(self::SHIPPING_ADDRESS) && $this->movesAwayFromProspectiveAddress($parameters, $context)) {
            // the stored address would no longer be used, so it is removed instead of reappearing after switching back
            $parameters[SalesChannelContextService::PROSPECTIVE_CUSTOMER] = null;
        }

        $customer = $context->getCustomer();
        $this->contextPersister->save(
            $context->getToken(),
            $parameters,
            $salesChannelId,
            $customer && $context->getPermissions() === [] ? $customer->getId() : null
        );

        // Language was switched - Check new Domain with old context
        $changeUrl = $this->checkNewDomain($parameters, $context);

        // Update the context with the new data, to have it up2date for the remainder of the request
        $context = $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $context->getSalesChannelId(),
                $context->getToken()
            )
        );

        $event = new SalesChannelContextSwitchEvent($context, $data);
        $this->eventDispatcher->dispatch($event);

        return new ContextTokenResponse($context->getToken(), $changeUrl);
    }

    private function createAddressDefinition(): DataValidationDefinition
    {
        // only the country is required, so that a partial address can be stored while it is being typed
        return (new DataValidationDefinition('context_switch.prospective_address'))
            ->add('countryId', new NotBlank(), new Type('string'))
            ->add('countryStateId', new Type('string'))
            ->add('zipcode', new Type('string'), new Length(max: CustomerAddressDefinition::MAX_LENGTH_ZIPCODE))
            ->add('city', new Type('string'), new Length(max: 255), new NoHtml())
            ->add('street', new Type('string'), new Length(max: 255), new NoHtml());
    }

    /**
     * Runs after the country is known to exist, because the zip code validator loads it.
     *
     * @param array<mixed> $parameters
     */
    private function validateZipCodes(array $parameters): void
    {
        $definition = new DataValidationDefinition('context_switch');

        foreach ([self::SHIPPING_ADDRESS, self::BILLING_ADDRESS] as $field) {
            $address = ProspectiveAddress::fromPayload($parameters[$field] ?? null);
            if ($address?->zipcode !== null) {
                $definition->addSub($field, (new DataValidationDefinition())->add('zipcode', new CustomerZipCode(countryId: $address->countryId)));
            }
        }

        $this->validator->validate($parameters, $definition);
    }

    /**
     * @param array<mixed> $parameters
     */
    private function movesAwayFromProspectiveAddress(array $parameters, SalesChannelContext $context): bool
    {
        $address = $context->getProspectiveCustomer()?->getShippingAddress();
        if ($address === null) {
            return false;
        }

        if (\array_key_exists(self::COUNTRY_ID, $parameters) && $parameters[self::COUNTRY_ID] !== $address->countryId) {
            return true;
        }

        return \array_key_exists(self::STATE_ID, $parameters) && ($parameters[self::STATE_ID] ?: null) !== $address->countryStateId;
    }

    /**
     * Moves the address fields into the payload of the `ProspectiveCustomer`. Every address field is written, even when it is empty,
     * so that a new address replaces the stored one instead of being merged into it.
     *
     * @param array<mixed> $parameters
     *
     * @return array<mixed>
     */
    private function toProspectiveCustomer(array $parameters): array
    {
        if (\array_key_exists(self::BILLING_ADDRESS, $parameters)) {
            $parameters[SalesChannelContextService::PROSPECTIVE_CUSTOMER][self::BILLING_ADDRESS] = ProspectiveAddress::fromPayload($parameters[self::BILLING_ADDRESS])?->toPayload();
        }

        if (\array_key_exists(self::SHIPPING_ADDRESS, $parameters)) {
            $shippingAddress = ProspectiveAddress::fromPayload($parameters[self::SHIPPING_ADDRESS]);

            if ($shippingAddress === null) {
                // without a shipping address, the stored billing address is forgotten as well
                $parameters[SalesChannelContextService::PROSPECTIVE_CUSTOMER] = null;
            } else {
                $parameters[SalesChannelContextService::PROSPECTIVE_CUSTOMER][self::SHIPPING_ADDRESS] = $shippingAddress->toPayload();
                // the context country is the address country, so the context factory can build the shipping location from both
                $parameters[self::COUNTRY_ID] = $shippingAddress->countryId;
                $parameters[self::STATE_ID] = $shippingAddress->countryStateId;
            }
        }

        unset($parameters[self::SHIPPING_ADDRESS], $parameters[self::BILLING_ADDRESS]);

        return $parameters;
    }

    /**
     * @param array<mixed> $parameters
     */
    private function checkNewDomain(array $parameters, SalesChannelContext $context): ?string
    {
        if (
            !isset($parameters[self::LANGUAGE_ID])
            || $parameters[self::LANGUAGE_ID] === $context->getLanguageId()
        ) {
            return null;
        }

        $domains = $context->getSalesChannel()->getDomains();
        if ($domains === null) {
            return null;
        }

        $langDomain = $domains->filterByProperty('languageId', $parameters[self::LANGUAGE_ID])->first();
        if ($langDomain === null) {
            return null;
        }

        return $langDomain->getUrl();
    }
}
