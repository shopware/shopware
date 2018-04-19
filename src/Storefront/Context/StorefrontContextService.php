<?php
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

namespace Shopware\Storefront\Context;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Context\Service\ContextFactoryInterface;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\ShopScope;
use Shopware\Serializer\SerializerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class StorefrontContextService implements StorefrontContextServiceInterface
{
    const FALLBACK_CUSTOMER_GROUP = 'EK';

    const CACHE_LIFETIME = 3600;

    /**
     * @var ContextFactoryInterface
     */
    private $factory;

    /**
     * @var \Shopware\Bundle\StoreFrontBundle\Common\CacheInterface
     */
    private $cache;

    /**
     * @var SerializerRegistry
     */
    private $serializerRegistry;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        RequestStack $requestStack,
        ContextFactoryInterface $factory,
        CacheItemPoolInterface $cache,
        SerializerRegistry $serializerRegistry
    ) {
        $this->requestStack = $requestStack;
        $this->factory = $factory;
        $this->cache = $cache;
        $this->serializerRegistry = $serializerRegistry;
    }

    public function getShopContext(): ShopContext
    {
        return $this->load(true);
    }

    public function refresh(): void
    {
        $this->load(false);
    }

    private function load(bool $useCache): ShopContext
    {
        $shopScope = new ShopScope(
            $this->getStoreFrontShopId(),
            $this->getStoreFrontCurrencyId()
        );

        $customerScope = new CustomerScope(
            $this->getStoreCustomerId(),
            null,
            $this->getStoreFrontBillingAddressId(),
            $this->getStoreFrontShippingAddressId()
        );

        $checkoutScope = new CheckoutScope(
            $this->getStoreFrontPaymentId(),
            $this->getStoreFrontDispatchId(),
            $this->getStoreFrontCountryId(),
            $this->getStoreFrontStateId()
        );

        $inputKey = $this->getCacheKey($shopScope, $customerScope, $checkoutScope);

        $cacheItem = $this->cache->getItem($inputKey);
        if ($useCache && $context = $cacheItem->get()) {
            return $this->serializerRegistry->deserialize($context, SerializerRegistry::FORMAT_JSON);
        }

        $context = $this->factory->create($shopScope, $customerScope, $checkoutScope);

        $outputKey = $this->getCacheKey(
            ShopScope::createFromContext($context),
            CustomerScope::createFromContext($context),
            CheckoutScope::createFromContext($context)
        );

        $data = $this->serializerRegistry->serialize($context, SerializerRegistry::FORMAT_JSON);

        $outputCacheItem = $this->cache->getItem($outputKey);

        $cacheItem->set($data);
        $outputCacheItem->set($data);

        $this->cache->save($cacheItem);
        $this->cache->save($outputCacheItem);

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
            json_encode($checkoutScope)
        );
    }

    /**
     * @return int
     */
    private function getStoreFrontShopId(): int
    {
        return $this->requestStack->getMasterRequest()->attributes->getInt('_shop_id');
    }

    /**
     * @return int
     */
    private function getStoreFrontCurrencyId(): int
    {
        return $this->requestStack->getMasterRequest()->attributes->getInt('_currency_id');
    }

    /**
     * @return int|null
     */
    private function getStoreFrontCountryId(): ?int
    {
        if ($countryId = $this->getSessionValueOrNull('sCountry')) {
            return (int) $countryId;
        }

        return null;
    }

    /**
     * @return int|null
     */
    private function getStoreFrontStateId(): ?int
    {
        if ($stateId = $this->getSessionValueOrNull('sState')) {
            return (int) $stateId;
        }

        return null;
    }

    private function getStoreCustomerId(): ?int
    {
        if ($customerId = $this->getSessionValueOrNull('sUserId')) {
            return (int) $customerId;
        }

        return null;
    }

    private function getStoreFrontBillingAddressId(): ?int
    {
        if ($addressId = $this->getSessionValueOrNull('checkoutBillingAddressId')) {
            return (int) $addressId;
        }

        return null;
    }

    private function getStoreFrontShippingAddressId(): ?int
    {
        if ($addressId = $this->getSessionValueOrNull('checkoutShippingAddressId')) {
            return (int) $addressId;
        }

        return null;
    }

    private function getStoreFrontPaymentId(): ?int
    {
        if ($paymentId = $this->getSessionValueOrNull('paymentMethodId')) {
            return (int) $paymentId;
        }

        return null;
    }

    private function getStoreFrontDispatchId(): ?int
    {
        if ($dispatchId = $this->getSessionValueOrNull('shippingMethodId')) {
            return (int) $dispatchId;
        }

        return null;
    }

    private function getSessionValueOrNull(string $key)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$session = $request->getSession()) {
            return null;
        }

        if (!$session->has($key)) {
            return null;
        }

        return $session->get($key);
    }
}
