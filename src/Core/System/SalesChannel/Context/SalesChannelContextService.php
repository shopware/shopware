<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelContextService implements SalesChannelContextServiceInterface
{
    public const CURRENCY_ID = 'currencyId';

    public const LANGUAGE_ID = 'languageId';

    public const CUSTOMER_ID = 'customerId';

    public const CUSTOMER_GROUP_ID = 'customerGroupId';

    public const BILLING_ADDRESS_ID = 'billingAddressId';

    public const SHIPPING_ADDRESS_ID = 'shippingAddressId';

    public const PAYMENT_METHOD_ID = 'paymentMethodId';

    public const SHIPPING_METHOD_ID = 'shippingMethodId';

    public const COUNTRY_ID = 'countryId';

    public const COUNTRY_STATE_ID = 'countryStateId';

    public const VERSION_ID = 'version-id';

    public const PERMISSIONS = 'permissions';

    public const DOMAIN_ID = 'domainId';

    public const ORIGINAL_CONTEXT = 'originalContext';

    /**
     * @var AbstractSalesChannelContextFactory
     */
    private $factory;

    /**
     * @var CartRuleLoader
     */
    private $ruleLoader;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        AbstractSalesChannelContextFactory $factory,
        CartRuleLoader $ruleLoader,
        SalesChannelContextPersister $contextPersister,
        CartService $cartService
    ) {
        $this->factory = $factory;
        $this->ruleLoader = $ruleLoader;
        $this->contextPersister = $contextPersister;
        $this->cartService = $cartService;
    }

    public function get(SalesChannelContextServiceParameters $parameters): SalesChannelContext
    {
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

        $context = $this->factory->create($token, $parameters->getSalesChannelId(), $session);

        $result = $this->ruleLoader->loadByToken($context, $token);

        $this->cartService->setCart($result->getCart());

        return $context;
    }
}
