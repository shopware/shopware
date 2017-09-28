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

namespace Shopware\Storefront\Context;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Context\Service\ContextFactoryInterface;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\ShopScope;
use Shopware\Serializer\SerializerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class StorefrontContextService implements StorefrontContextServiceInterface
{
    const FALLBACK_CUSTOMER_GROUP = '3294e6f6-372b-415f-ac73-71cbc191548f';

    /**
     * @var ContextFactoryInterface
     */
    private $factory;

    /**
     * @var CacheItemPoolInterface
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

    /**
     * @var TokenStorageInterface
     */
    private $securityTokenStorage;

    public function __construct(
        RequestStack $requestStack,
        ContextFactoryInterface $factory,
        CacheItemPoolInterface $cache,
        SerializerRegistry $serializerRegistry,
        TokenStorageInterface $securityTokenStorage
    ) {
        $this->requestStack = $requestStack;
        $this->factory = $factory;
        $this->cache = $cache;
        $this->serializerRegistry = $serializerRegistry;
        $this->securityTokenStorage = $securityTokenStorage;
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
            $this->getStorefrontShopUuid(),
            $this->getStorefrontCurrencyUuid()
        );

        $customerScope = new CustomerScope(
            $this->getStorefrontCustomerUuid(),
            null,
            $this->getStorefrontBillingAddressUuid(),
            $this->getStorefrontShippingAddressUuid()
        );

        $checkoutScope = new CheckoutScope(
            $this->getStorefrontPaymentMethodUuid(),
            $this->getStorefrontShippingMethodUuid(),
            $this->getStorefrontCountryUuid(),
            $this->getStorefrontStateId()
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

    private function getStorefrontShopUuid(): string
    {
        return $this->requestStack->getMasterRequest()->attributes->get('_shop_uuid');
    }

    private function getStorefrontCurrencyUuid(): string
    {
        return $this->requestStack->getMasterRequest()->attributes->get('_currency_uuid');
    }

    private function getStorefrontCountryUuid(): ?string
    {
        if ($countryId = $this->getSessionValueOrNull('country_uuid')) {
            return (string) $countryId;
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function getStorefrontStateId(): ?string
    {
        if ($stateId = $this->getSessionValueOrNull('state_uuid')) {
            return (string) $stateId;
        }

        return null;
    }

    private function getStorefrontCustomerUuid(): ?string
    {
        $token = $this->securityTokenStorage->getToken();

        if ($token && $token->getUser() && $token->getUser() instanceof UserInterface) {
            return $token->getUser()->getUuid();
        }

        return null;
    }

    private function getStorefrontBillingAddressUuid(): ?string
    {
        if ($addressId = $this->getSessionValueOrNull('checkout_billing_address_uuid')) {
            return (string) $addressId;
        }

        return null;
    }

    private function getStorefrontShippingAddressUuid(): ?string
    {
        if ($addressId = $this->getSessionValueOrNull('checkout_shipping_address_uuid')) {
            return (string) $addressId;
        }

        return null;
    }

    private function getStorefrontPaymentMethodUuid(): ?string
    {
        if ($paymentId = $this->getSessionValueOrNull('payment_method_uuid')) {
            return (string) $paymentId;
        }

        return null;
    }

    private function getStorefrontShippingMethodUuid(): ?string
    {
        if ($dispatchId = $this->getSessionValueOrNull('shipping_method_uuid')) {
            return (string) $dispatchId;
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
