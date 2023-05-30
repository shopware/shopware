<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('core')]
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

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $factory,
        private readonly CartRuleLoader $ruleLoader,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly CartService $cartService,
        private readonly EventDispatcherInterface $eventDispatcher
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

            if ($parameters->getCurrencyId() !== null && !\array_key_exists(self::CURRENCY_ID, $session)) {
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

            $context = $this->factory->create($token, $parameters->getSalesChannelId(), $session);
            $this->eventDispatcher->dispatch(new SalesChannelContextCreatedEvent($context, $token));

            $result = $this->ruleLoader->loadByToken($context, $token);

            $this->cartService->setCart($result->getCart());

            return $context;
        });
    }
}
