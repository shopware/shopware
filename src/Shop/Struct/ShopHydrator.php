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

namespace Shopware\Shop\Struct;

use Shopware\Category\Struct\CategoryHydrator;
use Shopware\Country\Struct\CountryHydrator;
use Shopware\Currency\Struct\CurrencyHydrator;
use Shopware\CustomerGroup\Struct\CustomerGroupHydrator;
use Shopware\Framework\Struct\Hydrator;
use Shopware\Locale\Struct\LocaleHydrator;
use Shopware\PaymentMethod\Struct\PaymentMethodHydrator;
use Shopware\ShippingMethod\Struct\ShippingMethodHydrator;
use Shopware\ShopTemplate\Struct\ShopTemplateHydrator;

class ShopHydrator extends Hydrator
{
    /**
     * @var ShopTemplateHydrator
     */
    private $templateHydrator;

    /**
     * @var CategoryHydrator
     */
    private $categoryHydrator;

    /**
     * @var LocaleHydrator
     */
    private $localeHydrator;

    /**
     * @var CurrencyHydrator
     */
    private $currencyHydrator;

    /**
     * @var CustomerGroupHydrator
     */
    private $customerGroupHydrator;

    /**
     * @var CountryHydrator
     */
    private $countryHydrator;

    /**
     * @var PaymentMethodHydrator
     */
    private $paymentMethodHydrator;

    /**
     * @var ShippingMethodHydrator
     */
    private $shippingMethodHydrator;

    public function __construct(
        ShopTemplateHydrator $templateHydrator,
        CategoryHydrator $categoryHydrator,
        LocaleHydrator $localeHydrator,
        CurrencyHydrator $currencyHydrator,
        CustomerGroupHydrator $customerGroupHydrator,
        CountryHydrator $countryHydrator,
        PaymentMethodHydrator $paymentMethodHydrator,
        ShippingMethodHydrator $shippingMethodHydrator
    ) {
        $this->templateHydrator = $templateHydrator;
        $this->categoryHydrator = $categoryHydrator;
        $this->localeHydrator = $localeHydrator;
        $this->currencyHydrator = $currencyHydrator;
        $this->customerGroupHydrator = $customerGroupHydrator;
        $this->countryHydrator = $countryHydrator;
        $this->paymentMethodHydrator = $paymentMethodHydrator;
        $this->shippingMethodHydrator = $shippingMethodHydrator;
    }

    public function hydrateIdentity(array $data): ShopIdentity
    {
        $identity = new ShopIdentity();
        $this->assignData($data, $identity);
        return $identity;
    }

    public function hydrateDetail(array $data): Shop
    {
        $shop = new Shop();
        $this->assignData($data, $shop);

        $shop->setCurrency($this->currencyHydrator->hydrate($data));
        $shop->setCustomerGroup($this->customerGroupHydrator->hydrate($data));
        $shop->setCategory($this->categoryHydrator->hydrateIdentity($data));
        $shop->setTemplate($this->templateHydrator->hydrate($data));
        $shop->setPaymentMethod($this->paymentMethodHydrator->hydrate($data));
        $shop->setShippingMethod($this->shippingMethodHydrator->hydrate($data));
        $shop->setCountry($this->countryHydrator->hydrateIdentity($data));

        return $shop;
    }

    protected function assignData(array $data, ShopIdentity $shop): void
    {
        $data['__shop_base_url'] = rtrim($data['__shop_base_url'], '/').'/';
        $data['__shop_base_path'] = rtrim($data['__shop_base_path'], '/').'/';

        $shop->setId((int) $data['__shop_id']);
        $shop->setUuid($data['__shop_uuid']);
        $shop->setMainId($data['__shop_main_id'] ? (int) $data['__shop_main_id'] : (int) $data['__shop_id']);
        $shop->setName($data['__shop_name']);
        $shop->setTitle($data['__shop_title']?: null);
        $shop->setPosition((int) $data['__shop_position']);
        $shop->setHost((string) $data['__shop_host']);
        $shop->setBasePath((string) $data['__shop_base_path']);
        $shop->setBaseUrl((string) $data['__shop_base_url']);
        $shop->setHosts(array_filter(explode("\n", $data['__shop_hosts'])));
        $shop->setSecure((bool) $data['__shop_secure']);
        $shop->setTemplateId((int) $data['__shop_template_id']);
        $shop->setDocumentTemplateId((int) $data['__shop_document_template_id']);
        $shop->setCategoryId((int) $data['__shop_category_id']);
        $shop->setCustomerGroupId((int) $data['__shop_customer_group_id']);
        $shop->setFallbackId($data['__shop_fallback_id'] ? (int)$data['__shop_fallback_id'] : null);
        $shop->setCustomerScope((int) $data['__shop_customer_scope']);
        $shop->setDefault((bool) $data['__shop_default']);
        $shop->setActive((bool) $data['__shop_active']);
        $shop->setPaymentId((int) $data['__shop_payment_id']);
        $shop->setDispatchId((int) $data['__shop_dispatch_id']);
        $shop->setCountryId((int) $data['__shop_country_id']);
        $shop->setTaxCalculationType($data['__shop_tax_calculation_type']);

        $shop->setCurrency($this->currencyHydrator->hydrate($data));
        $shop->setLocale($this->localeHydrator->hydrate($data));
    }
}
