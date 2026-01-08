<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

#[Package('framework')]
class SalesChannelContextService implements SalesChannelContextServiceInterface
{
    final public const CURRENCY_ID = 'currencyId';

    final public const LANGUAGE_ID = 'languageId';

    final public const CUSTOMER_ID = 'customerId';

    final public const CUSTOMER_GROUP_ID = 'customerGroupId';

    final public const BILLING_ADDRESS_ID = 'billingAddressId';

    final public const SHIPPING_ADDRESS_ID = 'shippingAddressId';

    final public const PAYMENT_METHOD_ID = 'paymentMethodId';

    final public const SHIPPING_METHOD_ID = 'shippingMethodId';

    final public const COUNTRY_ID = 'countryId';

    final public const COUNTRY_STATE_ID = 'countryStateId';

    final public const VERSION_ID = 'version-id';

    final public const PERMISSIONS = 'permissions';

    final public const DOMAIN_ID = 'domainId';

    final public const ORIGINAL_CONTEXT = 'originalContext';

    final public const IMITATING_USER_ID = 'imitatingUserId';

    /**
     * @internal do not rely on this externally, use the rules from the context instead
     */
    final public const RULE_IDS = 'sw-rule-ids';

    /**
     * @internal do not rely on this externally, use the rules from the context instead
     */
    final public const AREA_RULE_IDS = 'sw-rule-area-ids';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $factory,
        private readonly CartRuleLoader $ruleLoader,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly CartService $cartService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack
    ) {
    }

    public function get(SalesChannelContextServiceParameters $parameters): SalesChannelContext
    {
        return Profiler::trace('sales-channel-context', function () use ($parameters) {
            $token = $parameters->getToken();

            $session = $this->contextPersister->load($token, $parameters->getSalesChannelId());

            if ($session['expired'] ?? false) {
                $token = Random::getAlphanumericString(32);
            }

            if ($parameters->getLanguageId() !== null) {
                $session[self::LANGUAGE_ID] = $parameters->getLanguageId();
            }

            if ($parameters->getOverwriteCurrencyId() !== null) {
                $session[self::CURRENCY_ID] = $parameters->getOverwriteCurrencyId();
            } elseif ($parameters->getCurrencyId() !== null && !\array_key_exists(self::CURRENCY_ID, $session)) {
                $session[self::CURRENCY_ID] = $parameters->getCurrencyId();
            }

            if ($parameters->getDomainId() !== null) {
                $session[self::DOMAIN_ID] = $parameters->getDomainId();
            }

            if ($parameters->getOriginalContext() !== null) {
                $session[self::ORIGINAL_CONTEXT] = $parameters->getOriginalContext();
            }

            if ($parameters->getCustomerId() !== null) {
                $session[self::CUSTOMER_ID] = $parameters->getCustomerId();
            }

            if ($parameters->getImitatingUserId() !== null) {
                $session[self::IMITATING_USER_ID] = $parameters->getImitatingUserId();
            }

            $context = $this->factory->create($token, $parameters->getSalesChannelId(), $session);

            if ($parameters->getOriginalContext()?->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE)) {
                $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
            }

            $this->eventDispatcher->dispatch(new SalesChannelContextCreatedEvent($context, $token, $session));

            $currentRequest = $this->requestStack->getCurrentRequest();

            if ($currentRequest !== null) {
                // Update attributes and headers of the current request
                $currentRequest->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
                $currentRequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
                $currentRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());
            }

            $requestSession = $currentRequest?->hasSession() ? $currentRequest->getSession() : null;

            // Remove imitating user id from session, if there is no customer
            if ($requestSession && $context->getImitatingUserId() && !$context->getCustomerId()) {
                $requestSession->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
                $context->setImitatingUserId(null);
            }

            // skip cart calculation on ESI sub-requests if it has already been done.
            $esiRequest = $currentRequest?->attributes->has('_sw_esi') ?? false;
            if (!$this->cartService->hasCart($token) || !$esiRequest) {
                // @deprecated tag:v6.8.0 - Permission will always be true
                $result = $context->withPermissions(
                    [AbstractCartPersister::PERSIST_CART_ERROR_PERMISSION => Feature::isActive('DEFERRED_CART_ERRORS')],
                    fn (SalesChannelContext $context) => $this->ruleLoader->loadByToken($context, $token),
                );

                $this->cartService->setCart($result->getCart());

                // the rule loader updates the rules in the context, save them to the session for later reuse
                $requestSession?->set(self::RULE_IDS, $context->getRuleIds());
                $requestSession?->set(self::AREA_RULE_IDS, $context->getAreaRuleIds());
            } else {
                $context->setRuleIds($requestSession?->get(self::RULE_IDS) ?? []);
                $context->setAreaRuleIds($requestSession?->get(self::AREA_RULE_IDS) ?? []);
            }

            return $context;
        });
    }
}
