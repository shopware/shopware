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

namespace Shopware\Context\Struct;

use Shopware\Api\Context\Collection\ContextRuleBasicCollection;
use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Api\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Api\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Api\Shop\Struct\ShopDetailStruct;
use Shopware\Api\Tax\Collection\TaxBasicCollection;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Defaults;
use Shopware\Framework\Struct\Struct;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class StorefrontContext extends Struct
{
    /**
     * @var CustomerGroupBasicStruct
     */
    protected $currentCustomerGroup;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $fallbackCustomerGroup;

    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var ShopDetailStruct
     */
    protected $shop;

    /**
     * @var TaxBasicCollection
     */
    protected $taxRules;

    /**
     * @var CustomerBasicStruct|null
     */
    protected $customer;

    /**
     * @var PaymentMethodBasicStruct
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

    /**
     * @var ShippingLocation
     */
    protected $shippingLocation;

    /**
     * @var array
     */
    protected $contextRulesIds;

    /**
     * @var bool
     */
    protected $rulesLocked;

    public function __construct(
        ShopDetailStruct $shop,
        CurrencyBasicStruct $currency,
        CustomerGroupBasicStruct $currentCustomerGroup,
        CustomerGroupBasicStruct $fallbackCustomerGroup,
        TaxBasicCollection $taxRules,
        PaymentMethodBasicStruct $paymentMethod,
        ShippingMethodBasicStruct $shippingMethod,
        ShippingLocation $shippingLocation,
        ?CustomerBasicStruct $customer,
        array $contextRulesIds = []
    ) {
        $this->currentCustomerGroup = $currentCustomerGroup;
        $this->fallbackCustomerGroup = $fallbackCustomerGroup;
        $this->currency = $currency;
        $this->shop = $shop;
        $this->taxRules = $taxRules;
        $this->customer = $customer;
        $this->paymentMethod = $paymentMethod;
        $this->shippingMethod = $shippingMethod;
        $this->shippingLocation = $shippingLocation;
        $this->contextRulesIds = $contextRulesIds;
        $this->rulesLocked = false;
    }

    public function getCurrentCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->currentCustomerGroup;
    }

    public function getFallbackCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->fallbackCustomerGroup;
    }

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function getShop(): ShopDetailStruct
    {
        return $this->shop;
    }

    public function getTaxRules(): TaxBasicCollection
    {
        return $this->taxRules;
    }

    public function getCustomer(): ?CustomerBasicStruct
    {
        return $this->customer;
    }

    public function getPaymentMethod(): PaymentMethodBasicStruct
    {
        return $this->paymentMethod;
    }

    public function getShippingMethod(): ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }

    public function getShippingLocation(): ShippingLocation
    {
        return $this->shippingLocation;
    }

    public function getShopContext(): ShopContext
    {
        return new ShopContext(
            $this->shop->getId(),
            [Defaults::CATALOGUE],
            $this->contextRulesIds,
            $this->currency->getId(),
            $this->shop->getId(),
            $this->shop->getFallbackTranslationId(),
            Defaults::LIVE_VERSION,
            $this->currency->getFactor()
        );
    }

    public function getContextRulesIds(): array
    {
        return $this->contextRulesIds;
    }

    public function setContextRulesIds(array $ruleIds): void
    {
        if ($this->rulesLocked) {
            throw new \RuntimeException(
                sprintf('Context rules can not be switch any more')
            );
        }

        $this->contextRulesIds = array_values($ruleIds);
    }

    public function lockRules()
    {
        $this->rulesLocked = true;
    }
}
