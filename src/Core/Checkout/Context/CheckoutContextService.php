<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CheckoutContextService implements CheckoutContextServiceInterface
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
     * @var CheckoutContextFactoryInterface
     */
    private $factory;

    /**
     * @var CheckoutRuleLoader
     */
    private $ruleLoader;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    public function __construct(
        CacheItemPoolInterface $cache,
        CheckoutContextFactoryInterface $factory,
        CheckoutRuleLoader $ruleLoader,
        CheckoutContextPersister $contextPersister
    ) {
        $this->cache = $cache;
        $this->factory = $factory;
        $this->ruleLoader = $ruleLoader;
        $this->contextPersister = $contextPersister;
    }

    public function get(string $salesChannelId, string $token): CheckoutContext
    {
        return $this->load($salesChannelId, $token, true);
    }

    public function refresh(string $salesChannelId, string $token): void
    {
        $this->load($salesChannelId, $token, false);
    }

    private function load(string $salesChannelId, string $token, bool $useCache): CheckoutContext
    {
        $key = $salesChannelId . '-' . $token;

        $parameters = $this->contextPersister->load($token);

        $cacheKey = $key . '-' . implode($parameters);

        $item = $this->cache->getItem($cacheKey);

        $context = null;
        if ($useCache && $item->isHit()) {
            try {
                $context = $this->loadFromCache($item, $token);
            } catch (\Exception $e) {
            }
        }

        if (!$context) {
            $context = $this->factory->create($token, $salesChannelId, $parameters);

            $item->set(serialize($context));

            $item->expiresAfter(120);

            $this->cache->save($item);
        }

        $rules = $this->ruleLoader->loadMatchingRules($context, $token);
        $context->setRuleIds($rules->getIds());
        $context->lockRules();

        return $context;
    }

    private function loadFromCache(CacheItemInterface $item, string $token): CheckoutContext
    {
        $cacheContext = unserialize($item->get(), [Struct::class]);

        /* @var CheckoutContext $cacheContext */
        return new CheckoutContext(
            $token,
            $cacheContext->getSalesChannel(),
            $cacheContext->getLanguage(),
            $cacheContext->getFallbackLanguage(),
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
