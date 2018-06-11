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

namespace Shopware\Core\Checkout;

use Shopware\Core\System\Touchpoint\Struct\TouchpointBasicStruct;
use Shopware\Core\Checkout\DiscountSurcharge\Exception\RulesLockedException;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Core\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Core\Checkout\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Core\Checkout\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Currency\Struct\CurrencyBasicStruct;
use Shopware\Core\System\Tax\Collection\TaxBasicCollection;

/**
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CheckoutContext extends Struct
{
    /**
     * Unique token for context, e.g. stored in session or provided in request headers
     *
     * @var string
     */
    protected $token;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $currentCustomerGroup;

    /**
     * @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Struct\CustomerGroupBasicStruct
     */
    protected $fallbackCustomerGroup;

    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var TouchpointBasicStruct
     */
    protected $touchpoint;

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
    protected $rulesIds;

    /**
     * @var bool
     */
    protected $rulesLocked = false;

    /**
     * @see CartPrice::TAX_STATE_GROSS, CartPrice::TAX_STATE_NET, CartPrice::TAX_STATE_FREE
     *
     * @var string
     */
    protected $taxState = CartPrice::TAX_STATE_GROSS;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    /**
     * @var null|LanguageBasicStruct
     */
    protected $fallbackLanguage;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $tenantId;

    public function __construct(
        string $tenantId,
        string $token,
        TouchpointBasicStruct $touchpoint,
        LanguageBasicStruct $language,
        ?LanguageBasicStruct $fallbackLanguage,
        CurrencyBasicStruct $currency,
        CustomerGroupBasicStruct $currentCustomerGroup,
        CustomerGroupBasicStruct $fallbackCustomerGroup,
        TaxBasicCollection $taxRules,
        PaymentMethodBasicStruct $paymentMethod,
        ShippingMethodBasicStruct $shippingMethod,
        ShippingLocation $shippingLocation,
        ?CustomerBasicStruct $customer,
        array $rulesIds = []
    ) {
        $this->currentCustomerGroup = $currentCustomerGroup;
        $this->fallbackCustomerGroup = $fallbackCustomerGroup;
        $this->currency = $currency;
        $this->touchpoint = $touchpoint;
        $this->taxRules = $taxRules;
        $this->customer = $customer;
        $this->paymentMethod = $paymentMethod;
        $this->shippingMethod = $shippingMethod;
        $this->shippingLocation = $shippingLocation;
        $this->rulesIds = $rulesIds;
        $this->token = $token;
        $this->language = $language;
        $this->fallbackLanguage = $fallbackLanguage;
        $this->tenantId = $tenantId;
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

    public function getTouchpoint(): TouchpointBasicStruct
    {
        return $this->touchpoint;
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

    public function getContext(): Context
    {
        if ($this->context) {
            return $this->context;
        }

        return $this->context = new Context(
            $this->tenantId,
            $this->touchpoint->getId(),
            $this->touchpoint->getCatalogIds(),
            $this->rulesIds,
            $this->currency->getId(),
            $this->language->getId(),
            $this->language->getParentId(),
            Defaults::LIVE_VERSION,
            $this->currency->getFactor()
        );
    }

    public function getRuleIds(): array
    {
        return $this->rulesIds;
    }

    public function setRuleIds(array $ruleIds): void
    {
        if ($this->rulesLocked) {
            throw new RulesLockedException();
        }

        $this->rulesIds = array_values($ruleIds);
    }

    public function lockRules()
    {
        $this->rulesLocked = true;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function getFallbackLanguage(): ?LanguageBasicStruct
    {
        return $this->fallbackLanguage;
    }

    public function getTaxState(): string
    {
        return $this->taxState;
    }

    public function setTaxState(string $taxState): void
    {
        $this->taxState = $taxState;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
