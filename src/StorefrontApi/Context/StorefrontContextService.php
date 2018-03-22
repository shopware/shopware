<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\StorefrontApi\Context;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Context\Service\ContextFactoryInterface;
use Shopware\Context\Service\ContextRuleLoader;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopScope;
use Shopware\Context\Struct\StorefrontContext;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class StorefrontContextService implements StorefrontContextServiceInterface
{
    public const CURRENCY_ID = 'currencyId';
    public const CUSTOMER_ID = 'customerId';
    public const CUSTOMER_GROUP_ID = 'customerGroupId';
    public const BILLING_ADDRESS_ID = 'billingAddressId';
    public const SHIPPING_ADDRESS_ID = 'shippingAddressId';
    public const PAYMENT_METHOD_ID = 'paymentMethodId';
    public const SHIPPING_METHOD_ID = 'shippingMethodId';
    public const COUNTRY_ID = 'countryId';
    public const STATE_ID = 'stateId';

    /**
     * @var ContextFactoryInterface
     */
    private $factory;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var StorefrontContext[]
     */
    private $context = [];

    /**
     * @var ContextRuleLoader
     */
    private $contextRuleLoader;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    public function __construct(
        ContextFactoryInterface $factory,
        CacheItemPoolInterface $cache,
        SerializerInterface $serializer,
        ContextRuleLoader $contextRuleLoader,
        LoggerInterface $logger,
        StorefrontContextPersister $contextPersister
    ) {
        $this->factory = $factory;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->contextRuleLoader = $contextRuleLoader;
        $this->logger = $logger;
        $this->contextPersister = $contextPersister;
    }

    public function getStorefrontContext(string $applicationId, string $token): StorefrontContext
    {
        return $this->load($applicationId, $token, true);
    }

    public function refresh(string $applicationId, string $token): void
    {
        $key = $applicationId . '-' . $token;
        $this->context[$key] = null;
        $this->load($applicationId, $token, false);
    }

    private function load(string $applicationId, string $token, bool $useCache): StorefrontContext
    {
        $key = $applicationId . '-' . $token;

        if (isset($this->context[$key])) {
            return $this->context[$key];
        }

        $parameters = $this->contextPersister->load($token);

        $shopScope = new ShopScope(
            $applicationId,
            $parameters[self::CURRENCY_ID] ?? null
        );

        $customerScope = new CustomerScope(
            $parameters[self::CUSTOMER_ID] ?? null,
            $parameters[self::CUSTOMER_GROUP_ID] ?? null,
            $parameters[self::BILLING_ADDRESS_ID] ?? null,
            $parameters[self::SHIPPING_ADDRESS_ID] ?? null
        );

        $checkoutScope = new CheckoutScope(
            $parameters[self::PAYMENT_METHOD_ID] ?? null,
            $parameters[self::SHIPPING_METHOD_ID] ?? null,
            $parameters[self::COUNTRY_ID] ?? null,
            $parameters[self::STATE_ID] ?? null,
            $token
        );

        $inputKey = $this->getCacheKey($shopScope, $customerScope, $checkoutScope);

        $cacheItem = $this->cache->getItem($inputKey);

        if ($useCache && $context = $cacheItem->get()) {
            try {
                return $this->loadFromCache($applicationId, $token, $context);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        $context = $this->factory->create($token, $shopScope, $customerScope, $checkoutScope);

        $this->writeCache($context, $cacheItem);

        $rules = $this->contextRuleLoader->loadMatchingRules($context, $checkoutScope->getCartToken());
        $context->setContextRulesIds($rules->getIds());
        $context->lockRules();

        $this->context[$key] = $context;

        return $context;
    }

    private function loadFromCache(string $applicationId, string $token, $json): StorefrontContext
    {
        $key = $applicationId . '-' . $token;

        $context = $this->serializer->deserialize($json, '', 'json');

        /** @var StorefrontContext $context */
        $context = new StorefrontContext(
            $token,
            $context->getShop(),
            $context->getCurrency(),
            $context->getCurrentCustomerGroup(),
            $context->getFallbackCustomerGroup(),
            $context->getTaxRules(),
            $context->getPaymentMethod(),
            $context->getShippingMethod(),
            $context->getShippingLocation(),
            $context->getCustomer(),
            []
        );

        $rules = $this->contextRuleLoader->loadMatchingRules($context, $token);
        $context->setContextRulesIds($rules->getIds());
        $context->lockRules();

        $this->context[$key] = $context;

        return $context;
    }

    private function getCacheKey(
        ShopScope $shopScope,
        CustomerScope $customerScope,
        CheckoutScope $checkoutScope
    ): string {
        return md5(
            json_encode($shopScope) .
            json_encode($customerScope) .
            json_encode([
                $checkoutScope->getShippingMethodId(),
                $checkoutScope->getPaymentMethodId(),
                $checkoutScope->getCountryId(),
                $checkoutScope->getStateId(),
            ])
        );
    }

    private function writeCache(StorefrontContext $context, CacheItemInterface $cacheItem): void
    {
        $outputKey = $this->getCacheKey(
            ShopScope::createFromContext($context),
            CustomerScope::createFromContext($context),
            CheckoutScope::createFromContext($context)
        );

        $data = $this->serializer->serialize($context, 'json');
        $outputCacheItem = $this->cache->getItem($outputKey);

        $cacheItem->set($data);
        $outputCacheItem->set($data);

        $this->cache->save($cacheItem);
        $this->cache->save($outputCacheItem);
    }
}
