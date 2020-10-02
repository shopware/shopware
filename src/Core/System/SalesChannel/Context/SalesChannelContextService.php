<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
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

    /**
     * @var SalesChannelContextFactory
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
        SalesChannelContextFactory $factory,
        CartRuleLoader $ruleLoader,
        SalesChannelContextPersister $contextPersister,
        CartService $cartService
    ) {
        $this->factory = $factory;
        $this->ruleLoader = $ruleLoader;
        $this->contextPersister = $contextPersister;
        $this->cartService = $cartService;
    }

    /**
     * @deprecated tag:v6.4.0 - Parameter $currencyId will be mandatory in future implementation
     */
    public function get(string $salesChannelId, string $token, ?string $languageId = null/*, ?string $currencyId */): SalesChannelContext
    {
        $parameters = $this->contextPersister->load($token);

        if ($languageId) {
            $parameters[self::LANGUAGE_ID] = $languageId;
        }

        if (func_num_args() >= 4 && !array_key_exists(self::CURRENCY_ID, $parameters)) {
            $currencyId = func_get_arg(3);

            if ($currencyId !== null) {
                $parameters[self::CURRENCY_ID] = $currencyId;
            }
        }

        $context = $this->factory->create($token, $salesChannelId, $parameters);

        $result = $this->ruleLoader->loadByToken($context, $token);

        $this->cartService->setCart($result->getCart());

        return $context;
    }
}
