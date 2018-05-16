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

namespace Shopware\Application\Context\Util;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Application\Context\Util\ContextFactoryInterface;
use Shopware\Application\Context\Util\ContextRuleLoader;
use Shopware\Application\Context\Struct\StorefrontContext;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class StorefrontContextService implements StorefrontContextServiceInterface
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

    public function getStorefrontContext(string $tenantId, string $applicationId, string $token): StorefrontContext
    {
        return $this->load($tenantId, $applicationId, $token, true);
    }

    public function refresh(string $tenantId, string $applicationId, string $token): void
    {
        $key = $applicationId . '-' . $token . '-' . $tenantId;
        $this->context[$key] = null;
        $this->load($tenantId, $applicationId, $token, false);
    }

    private function load(string $tenantId, string $applicationId, string $token, bool $useCache): StorefrontContext
    {
        $key = $applicationId . '-' . $token . '-' . $tenantId;

        if (isset($this->context[$key])) {
            return $this->context[$key];
        }

        $parameters = $this->contextPersister->load($token, $tenantId);

        $context = $this->factory->create($tenantId, $token, $applicationId, $parameters);

        $rules = $this->contextRuleLoader->loadMatchingRules($context, $token);
        $context->setContextRuleIds($rules->getIds());
        $context->lockRules();

        $this->context[$key] = $context;

        return $context;
    }

    private function loadFromCache(string $applicationId, string $token, $serializedContext): StorefrontContext
    {
        $key = $applicationId . '-' . $token;

        $cacheContext = $this->serializer->deserialize($serializedContext, '', 'json');

        /** @var StorefrontContext $cacheContext */
        $context = new StorefrontContext(
            $token,
            $cacheContext->getApplication(),
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

        $context->setTaxState($cacheContext->getTaxState());

        $rules = $this->contextRuleLoader->loadMatchingRules($context, $token);
        $context->setContextRuleIds($rules->getIds());
        $context->lockRules();

        $this->context[$key] = $context;

        return $context;
    }
}
