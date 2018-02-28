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
use Shopware\Cart\Cart\CartCollector;
use Shopware\Cart\Cart\CartProcessor;
use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Cart\CartValidator;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\Price\Struct\CartPrice;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\CartBridge\Service\StoreFrontCartService;
use Shopware\Context\Service\ContextFactoryInterface;
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
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    /**
     * @var CartCollector
     */
    private $cartCollector;

    /**
     * @var CartValidator
     */
    private $cartValidator;
    /**
     * @var CartProcessor
     */
    private $cartProcessor;

    public function __construct(
        RequestStack $requestStack,
        ContextFactoryInterface $factory,
        CacheItemPoolInterface $cache,
        SerializerInterface $serializer,
        TokenStorageInterface $securityTokenStorage,
        CartPersisterInterface $cartPersister,
        TaxDetector $taxDetector,
        CartCollector $cartCollector,
        CartProcessor $cartProcessor,
        CartValidator $cartValidator
    ) {
        $this->requestStack = $requestStack;
        $this->factory = $factory;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->securityTokenStorage = $securityTokenStorage;
        $this->cartPersister = $cartPersister;
        $this->taxDetector = $taxDetector;
        $this->cartCollector = $cartCollector;
        $this->cartValidator = $cartValidator;
        $this->cartProcessor = $cartProcessor;
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

                $context = $this->findMatchingContextRules($context, $checkoutScope);

                return $this->context = $context;
            } catch (\Exception $e) {
                //todo@dr log message
            }
        }

        $context = $this->factory->create($shopScope, $customerScope, $checkoutScope);

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

        $context = $this->findMatchingContextRules($context, $checkoutScope);

        return $this->context = $context;
    }

    private function findMatchingContextRules(StorefrontContext $context, CheckoutScope $checkoutScope)
    {
        try {
            $calculated = $this->cartPersister->loadCalculated(
                (string)$checkoutScope->getCartToken(),
                StoreFrontCartService::CART_NAME
            );
        } catch (CartTokenNotFoundException $e) {
            $calculated = new CalculatedCart(
                Cart::createNew(StoreFrontCartService::CART_NAME),
                new CalculatedLineItemCollection(),
                CartPrice::createEmpty($this->taxDetector->getTaxState($context)),
                new DeliveryCollection()
            );
        }

        $rules = $context->getContextRules();

        $valid = false;

        //first collect additional data for cart processors outside the loop to prevent duplicate database access
        $processorData = $this->cartCollector->collect($calculated->getCart(), $context);

        while (!$valid) {
            //find rules which matching current cart and context state
            $rules = $rules->filterMatchingRules($calculated, $context);

            //place rules into context for further usages
            $context->setContextRules($rules);

            //recalculate cart for new context rules
            $newCart = $this->cartProcessor->process($calculated->getCart(), $context, $processorData);

            //if cart isn't valid, return the context rule finding
            $valid = $this->cartValidator->isValid($calculated, $context);

            if ($this->cartChanged($calculated, $newCart)) {
                $valid = false;
                $calculated = $newCart;
            }
        }

        $context->lockRules();

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
        return $this->requestStack->getMasterRequest()->attributes->get('_shop_id');
    }

    private function getStorefrontCurrencyId(): string
    {
        return $this->requestStack->getMasterRequest()->attributes->get('_currency_id');
    }

    private function getStorefrontCountryId(): ?string
    {
        if ($countryId = $this->getSessionValueOrNull('country_id')) {
            return (string) $countryId;
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function getStorefrontStateId(): ?string
    {
        if ($stateId = $this->getSessionValueOrNull('state_id')) {
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
        if ($addressId = $this->getSessionValueOrNull('checkout_billing_address_id')) {
            return (string) $addressId;
        }

        return null;
    }

    private function getStorefrontShippingAddressId(): ?string
    {
        if ($addressId = $this->getSessionValueOrNull('checkout_shipping_address_id')) {
            return (string) $addressId;
        }

        return null;
    }

    private function getStorefrontPaymentMethodId(): ?string
    {
        if ($paymentId = $this->getSessionValueOrNull('payment_method_id')) {
            return (string) $paymentId;
        }

        return null;
    }

    private function getStorefrontShippingMethodId(): ?string
    {
        if ($dispatchId = $this->getSessionValueOrNull('shipping_method_id')) {
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

    private function getStorefrontCartToken(): ?string
    {
        return $this->getSessionValueOrNull(StoreFrontCartService::CART_TOKEN_KEY);
    }

    private function cartChanged(CalculatedCart $previous, CalculatedCart $current): bool
    {
        return md5(json_encode($previous)) !== md5(json_encode($current));
    }
}
