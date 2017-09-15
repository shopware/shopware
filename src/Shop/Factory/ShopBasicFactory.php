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

namespace Shopware\Shop\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Factory\CurrencyBasicFactory;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\Factory\Factory;
use Shopware\Locale\Factory\LocaleBasicFactory;
use Shopware\Locale\Struct\LocaleBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Shop\Extension\ShopExtension;
use Shopware\Shop\Struct\ShopBasicStruct;

class ShopBasicFactory extends Factory
{
    const ROOT_NAME = 'shop';

    const FIELDS = [
       'uuid' => 'uuid',
       'name' => 'name',
       'title' => 'title',
       'position' => 'position',
       'host' => 'host',
       'base_path' => 'base_path',
       'base_url' => 'base_url',
       'hosts' => 'hosts',
       'is_secure' => 'is_secure',
       'customer_scope' => 'customer_scope',
       'is_default' => 'is_default',
       'active' => 'active',
       'tax_calculation_type' => 'tax_calculation_type',
       'parent_uuid' => 'parent_uuid',
       'shop_template_uuid' => 'shop_template_uuid',
       'document_template_uuid' => 'document_template_uuid',
       'category_uuid' => 'category_uuid',
       'locale_uuid' => 'locale_uuid',
       'currency_uuid' => 'currency_uuid',
       'customer_group_uuid' => 'customer_group_uuid',
       'fallback_locale_uuid' => 'fallback_locale_uuid',
       'payment_method_uuid' => 'payment_method_uuid',
       'shipping_method_uuid' => 'shipping_method_uuid',
       'area_country_uuid' => 'area_country_uuid',
    ];

    /**
     * @var ShopExtension[]
     */
    protected $extensions = [];

    /**
     * @var CurrencyBasicFactory
     */
    protected $currencyFactory;

    /**
     * @var LocaleBasicFactory
     */
    protected $localeFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        CurrencyBasicFactory $currencyFactory,
        LocaleBasicFactory $localeFactory
    ) {
        parent::__construct($connection, $extensions);
        $this->currencyFactory = $currencyFactory;
        $this->localeFactory = $localeFactory;
    }

    public function hydrate(
        array $data,
        ShopBasicStruct $shop,
        QuerySelection $selection,
        TranslationContext $context
    ): ShopBasicStruct {
        $shop->setUuid((string) $data[$selection->getField('uuid')]);
        $shop->setName((string) $data[$selection->getField('name')]);
        $shop->setTitle(isset($data[$selection->getField('title')]) ? (string) $data[$selection->getField('title')] : null);
        $shop->setPosition((int) $data[$selection->getField('position')]);
        $shop->setHost((string) $data[$selection->getField('host')]);
        $shop->setBasePath((string) $data[$selection->getField('base_path')]);
        $shop->setBaseUrl((string) $data[$selection->getField('base_url')]);
        $shop->setHosts(isset($data[$selection->getField('hosts')]) ? (string) $data[$selection->getField('hosts')] : null);
        $shop->setIsSecure((bool) $data[$selection->getField('is_secure')]);
        $shop->setCustomerScope((bool) $data[$selection->getField('customer_scope')]);
        $shop->setIsDefault((bool) $data[$selection->getField('is_default')]);
        $shop->setActive((bool) $data[$selection->getField('active')]);
        $shop->setTaxCalculationType((string) $data[$selection->getField('tax_calculation_type')]);
        $shop->setParentUuid(isset($data[$selection->getField('parent_uuid')]) ? (string) $data[$selection->getField('parent_uuid')] : null);
        $shop->setTemplateUuid((string) $data[$selection->getField('shop_template_uuid')]);
        $shop->setDocumentTemplateUuid((string) $data[$selection->getField('document_template_uuid')]);
        $shop->setCategoryUuid((string) $data[$selection->getField('category_uuid')]);
        $shop->setLocaleUuid((string) $data[$selection->getField('locale_uuid')]);
        $shop->setCurrencyUuid((string) $data[$selection->getField('currency_uuid')]);
        $shop->setCustomerGroupUuid((string) $data[$selection->getField('customer_group_uuid')]);
        $shop->setFallbackLocaleUuid(isset($data[$selection->getField('fallback_locale_uuid')]) ? (string) $data[$selection->getField('fallback_locale_uuid')] : null);
        $shop->setPaymentMethodUuid(isset($data[$selection->getField('payment_method_uuid')]) ? (string) $data[$selection->getField('payment_method_uuid')] : null);
        $shop->setShippingMethodUuid(isset($data[$selection->getField('shipping_method_uuid')]) ? (string) $data[$selection->getField('shipping_method_uuid')] : null);
        $shop->setAreaCountryUuid(isset($data[$selection->getField('area_country_uuid')]) ? (string) $data[$selection->getField('area_country_uuid')] : null);
        $currency = $selection->filter('currency');
        if ($currency && !empty($data[$currency->getField('uuid')])) {
            $shop->setCurrency(
                $this->currencyFactory->hydrate($data, new CurrencyBasicStruct(), $currency, $context)
            );
        }
        $locale = $selection->filter('locale');
        if ($locale && !empty($data[$locale->getField('uuid')])) {
            $shop->setLocale(
                $this->localeFactory->hydrate($data, new LocaleBasicStruct(), $locale, $context)
            );
        }

        foreach ($this->extensions as $extension) {
            $extension->hydrate($shop, $data, $selection, $context);
        }

        return $shop;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['currency'] = $this->currencyFactory->getFields();
        $fields['locale'] = $this->localeFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($currency = $selection->filter('currency')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'currency',
                $currency->getRootEscaped(),
                sprintf('%s.uuid = %s.currency_uuid', $currency->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->currencyFactory->joinDependencies($currency, $query, $context);
        }

        if ($locale = $selection->filter('locale')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'locale',
                $locale->getRootEscaped(),
                sprintf('%s.uuid = %s.locale_uuid', $locale->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->localeFactory->joinDependencies($locale, $query, $context);
        }

        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'shop_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.shop_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['currency'] = $this->currencyFactory->getAllFields();
        $fields['locale'] = $this->localeFactory->getAllFields();

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }
}
