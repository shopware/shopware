<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
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

    public const STATE_ID = 'stateId';

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

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
        CacheItemPoolInterface $cache,
        SalesChannelContextFactory $factory,
        CartRuleLoader $ruleLoader,
        SalesChannelContextPersister $contextPersister,
        CartService $cartService
    ) {
        $this->cache = $cache;
        $this->factory = $factory;
        $this->ruleLoader = $ruleLoader;
        $this->contextPersister = $contextPersister;
        $this->cartService = $cartService;
    }

    public function get(string $salesChannelId, string $token, ?string $languageId = null): SalesChannelContext
    {
        return $this->load($salesChannelId, $token, true, $languageId);
    }

    public function refresh(string $salesChannelId, string $token, ?string $languageId = null): void
    {
        $this->load($salesChannelId, $token, false, $languageId);
    }

    private function load(string $salesChannelId, string $token, bool $useCache, ?string $languageId = null): SalesChannelContext
    {
        $key = $salesChannelId . '-' . $token;

        $parameters = $this->contextPersister->load($token);

        if ($languageId) {
            $parameters[self::LANGUAGE_ID] = $languageId;
        }

        $cacheKey = $key . '-' . implode($parameters);

        $item = $this->cache->getItem($cacheKey);

        $context = null;
        if ($useCache && $item->isHit()) {
            try {
                $context = $this->loadFromCache($item, $token);
            } catch (\Exception $e) {
                // nth
            }
        }

        if (!$context) {
            $context = $this->factory->create($token, $salesChannelId, $parameters);

            $item->set($context);

            $item->expiresAfter(120);

            $this->cache->save($item);
        }

        $result = $this->ruleLoader->loadByToken($context, $token);

        $this->cartService->setCart($result->getCart());

        return $context;
    }

    private function loadFromCache(CacheItemInterface $item, string $token): SalesChannelContext
    {
        $cacheContext = $item->get();

        /* @var SalesChannelContext $cacheContext */
        return new SalesChannelContext(
            $cacheContext->getContext(),
            $token,
            $cacheContext->getSalesChannel(),
            $cacheContext->getCurrency(),
            $cacheContext->getCurrentCustomerGroup(),
            $cacheContext->getFallbackCustomerGroup(),
            $cacheContext->getTaxRules(),
            $cacheContext->getPaymentMethod(),
            $cacheContext->getShippingMethod(),
            $cacheContext->getShippingLocation(),
            $cacheContext->getCustomer(),
            []
        );
    }
}
