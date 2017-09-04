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

namespace Shopware\Shop\Reader;

use Shopware\Currency\Reader\CurrencyBasicHydrator;
use Shopware\Framework\Struct\Hydrator;
use Shopware\Locale\Reader\LocaleBasicHydrator;
use Shopware\Shop\Struct\ShopBasicStruct;

class ShopBasicHydrator extends Hydrator
{
    /**
     * @var CurrencyBasicHydrator
     */
    private $currencyBasicHydrator;
    /**
     * @var LocaleBasicHydrator
     */
    private $localeBasicHydrator;

    public function __construct(CurrencyBasicHydrator $currencyBasicHydrator, LocaleBasicHydrator $localeBasicHydrator)
    {
        $this->currencyBasicHydrator = $currencyBasicHydrator;
        $this->localeBasicHydrator = $localeBasicHydrator;
    }

    public function hydrate(array $data): ShopBasicStruct
    {
        $shop = new ShopBasicStruct();

        $shop->setId((int) $data['__shop_id']);
        $shop->setUuid((string) $data['__shop_uuid']);
        $shop->setMainId(isset($data['__shop_main_id']) ? (int) $data['__shop_main_id'] : null);
        $shop->setName((string) $data['__shop_name']);
        $shop->setTitle(isset($data['__shop_title']) ? (string) $data['__shop_title'] : null);
        $shop->setPosition((int) $data['__shop_position']);
        $shop->setHost(isset($data['__shop_host']) ? (string) $data['__shop_host'] : null);
        $shop->setBasePath((string) $data['__shop_base_path']);
        $shop->setBaseUrl((string) $data['__shop_base_url']);
        $shop->setHosts((string) $data['__shop_hosts']);
        $shop->setSecure((bool) $data['__shop_secure']);
        $shop->setTemplateId(isset($data['__shop_shop_template_id']) ? (int) $data['__shop_shop_template_id'] : null);
        $shop->setDocumentTemplateId(isset($data['__shop_document_template_id']) ? (int) $data['__shop_document_template_id'] : null);
        $shop->setCategoryId(isset($data['__shop_category_id']) ? (int) $data['__shop_category_id'] : null);
        $shop->setLocaleId(isset($data['__shop_locale_id']) ? (int) $data['__shop_locale_id'] : null);
        $shop->setCurrencyId(isset($data['__shop_currency_id']) ? (int) $data['__shop_currency_id'] : null);
        $shop->setCustomerGroupId(isset($data['__shop_customer_group_id']) ? (int) $data['__shop_customer_group_id'] : null);
        $shop->setFallbackId(isset($data['__shop_fallback_id']) ? (int) $data['__shop_fallback_id'] : null);
        $shop->setCustomerScope((bool) $data['__shop_customer_scope']);
        $shop->setIsDefault((bool) $data['__shop_is_default']);
        $shop->setActive((bool) $data['__shop_active']);
        $shop->setPaymentMethodId((int) $data['__shop_payment_method_id']);
        $shop->setShippingMethodId((int) $data['__shop_shipping_method_id']);
        $shop->setAreaCountryId((int) $data['__shop_area_country_id']);
        $shop->setTaxCalculationType((string) $data['__shop_tax_calculation_type']);
        $shop->setMainUuid(isset($data['__shop_main_uuid']) ? (string) $data['__shop_main_uuid'] : null);
        $shop->setTemplateUuid(isset($data['__shop_shop_template_uuid']) ? (string) $data['__shop_shop_template_uuid'] : null);
        $shop->setDocumentTemplateUuid(isset($data['__shop_document_template_uuid']) ? (string) $data['__shop_document_template_uuid'] : null);
        $shop->setCategoryUuid((string) $data['__shop_category_uuid']);
        $shop->setLocaleUuid((string) $data['__shop_locale_uuid']);
        $shop->setCurrencyUuid((string) $data['__shop_currency_uuid']);
        $shop->setCustomerGroupUuid((string) $data['__shop_customer_group_uuid']);
        $shop->setFallbackLocaleUuid(isset($data['__shop_fallback_locale_uuid']) ? (string) $data['__shop_fallback_locale_uuid'] : null);
        $shop->setPaymentMethodUuid((string) $data['__shop_payment_method_uuid']);
        $shop->setShippingMethodUuid((string) $data['__shop_shipping_method_uuid']);
        $shop->setAreaCountryUuid((string) $data['__shop_area_country_uuid']);
        $shop->setCurrency($this->currencyBasicHydrator->hydrate($data));
        $shop->setLocale($this->localeBasicHydrator->hydrate($data));

        return $shop;
    }
}
