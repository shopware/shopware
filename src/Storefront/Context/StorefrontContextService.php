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
use Psr\Log\LoggerInterface;
use Shopware\CartBridge\Service\StoreFrontCartService;
use Shopware\Context\Service\ContextFactoryInterface;
use Shopware\Context\Service\ContextRuleLoader;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopScope;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Firewall\CustomerUser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class StorefrontContextService implements StorefrontContextServiceInterface
{
    const SESSION_COUNTRY_ID = 'country_id';

    const SESSION_STATE_ID = 'state_id';

    const SESSION_BILLING_ADDRESS_ID = 'checkout_billing_address_id';

    const SESSION_SHIPPING_ADDRESS_ID = 'checkout_shipping_address_id';

    const SESSION_PAYMENT_METHOD_ID = 'payment_method_id';

    const SESSION_SHIPPING_METHOD_ID = 'shipping_method_id';

    const ATTRIBUTES_SHOP_ID = '_shop_id';

    const ATTRIBUTES_CURRENCY_ID = '_currency_id';

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
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TokenStorageInterface
     */
    private $securityTokenStorage;

    /**
     * @var StorefrontContext
     */
    private $context;

    /**
     * @var ContextRuleLoader
     */
    private $contextRuleLoader;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        RequestStack $requestStack,
        ContextFactoryInterface $factory,
        CacheItemPoolInterface $cache,
        SerializerInterface $serializer,
        TokenStorageInterface $securityTokenStorage,
        ContextRuleLoader $contextRuleLoader,
        LoggerInterface $logger
    ) {
        $this->requestStack = $requestStack;
        $this->factory = $factory;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->securityTokenStorage = $securityTokenStorage;
        $this->contextRuleLoader = $contextRuleLoader;
        $this->logger = $logger;
    }

    public function getStorefrontContext(): StorefrontContext
    {
        return $this->load(true);
    }

    public function refresh(): void
    {
        $this->context = null;
        $this->load(false);
    }

    private function load(bool $useCache): StorefrontContext
    {
        if ($this->context) {
            return $this->context;
        }

        $shopScope = new ShopScope(
            $this->getStorefrontShopId(),
            $this->getStorefrontCurrencyId()
        );

        $customerScope = new CustomerScope(
            $this->getStorefrontCustomerId(),
            null,
            $this->getStorefrontBillingAddressId(),
            $this->getStorefrontShippingAddressId()
        );

        $checkoutScope = new CheckoutScope(
            $this->getStorefrontPaymentMethodId(),
            $this->getStorefrontShippingMethodId(),
            $this->getStorefrontCountryId(),
            $this->getStorefrontStateId(),
            $this->getStorefrontCartToken()
        );

        $inputKey = $this->getCacheKey($shopScope, $customerScope, $checkoutScope);
        $cacheItem = $this->cache->getItem($inputKey);
        if ($useCache && $context = $cacheItem->get()) {
            try {
                $context = $this->serializer->deserialize($context, '', 'json');
                $this->context = $context;

                $rules = $this->contextRuleLoader->loadMatchingRules($context, $checkoutScope->getCartToken());
                $this->context->setContextRulesIds($rules->getIds());
                $this->context->lockRules();

                return $this->context;
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        $context = $this->factory->create($shopScope, $customerScope, $checkoutScope);
        $this->context = $context;

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

        $rules = $this->contextRuleLoader->loadMatchingRules($context, $checkoutScope->getCartToken());
        $this->context->setContextRulesIds($rules->getIds());
        $this->context->lockRules();

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

    private function getStorefrontShopId(): string
    {
        return $this->requestStack->getMasterRequest()->attributes->get(self::ATTRIBUTES_SHOP_ID);
    }

    private function getStorefrontCurrencyId(): string
    {
        return $this->requestStack->getMasterRequest()->attributes->get(self::ATTRIBUTES_CURRENCY_ID);
    }

    private function getStorefrontCountryId(): ?string
    {
        if ($countryId = $this->getSessionValueOrNull(self::SESSION_COUNTRY_ID)) {
            return (string) $countryId;
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function getStorefrontStateId(): ?string
    {
        if ($stateId = $this->getSessionValueOrNull(self::SESSION_STATE_ID)) {
            return (string) $stateId;
        }

        return null;
    }

    private function getStorefrontCustomerId(): ?string
    {
        $token = $this->securityTokenStorage->getToken();

        if ($token && $token->getUser() && $token->getUser() instanceof CustomerUser) {
            return $token->getUser()->getId();
        }

        return null;
    }

    private function getStorefrontBillingAddressId(): ?string
    {
        if ($addressId = $this->getSessionValueOrNull(self::SESSION_BILLING_ADDRESS_ID)) {
            return (string) $addressId;
        }

        return null;
    }

    private function getStorefrontShippingAddressId(): ?string
    {
        if ($addressId = $this->getSessionValueOrNull(self::SESSION_SHIPPING_ADDRESS_ID)) {
            return (string) $addressId;
        }

        return null;
    }

    private function getStorefrontPaymentMethodId(): ?string
    {
        if ($paymentId = $this->getSessionValueOrNull(self::SESSION_PAYMENT_METHOD_ID)) {
            return (string) $paymentId;
        }

        return null;
    }

    private function getStorefrontShippingMethodId(): ?string
    {
        if ($dispatchId = $this->getSessionValueOrNull(self::SESSION_SHIPPING_METHOD_ID)) {
            return (string) $dispatchId;
        }

        return null;
    }

    private function getSessionValueOrNull(string $key)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        if (!$session = $request->getSession()) {
            return null;
        }

        if (!$session->has($key)) {
            return null;
        }

        return $session->get($key);
    }

    private function getStorefrontCartToken(): ?string
    {
        return $this->getSessionValueOrNull(StoreFrontCartService::CART_TOKEN_KEY);
    }
}
