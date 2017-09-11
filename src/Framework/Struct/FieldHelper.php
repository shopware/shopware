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

namespace Shopware\Framework\Struct;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Context\Struct\TranslationContext;

class FieldHelper
{
    private $attributeFields = [];

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(Connection $connection, CacheItemPoolInterface $cache)
    {
        $this->connection = $connection;
        $this->cache = $cache;
    }

    public function getTableFields($table, $alias): array
    {
        $key = $table;

        if (isset($this->attributeFields[$key])) {
            return $this->attributeFields[$key];
        }

        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        $tableColumns = $this->connection->fetchAll('SHOW COLUMNS FROM ' . $table);
        $tableColumns = array_column($tableColumns, 'Field');

        $columns = [];
        foreach ($tableColumns as $column) {
            $columns[] = $alias . '.' . $column . ' as __' . $alias . '_' . $column;
        }

        $item->set($columns);
        $this->cache->save($item);
        $this->attributeFields[$key] = $columns;

        return $columns;
    }

    public function getProductStreamFields(): array
    {
        return [
            'productStream.uuid as __array_key',
            'productStream.id as __productStream_id',
            'productStream.uuid as __productStream_uuid',
            'productStream.name as __productStream_name',
            'productStream.conditions as __productStream_conditions',
            'productStream.type as __productStream_type',
            'productStream.description as __productStream_description',
            'productStream.listing_sorting_id as __productStream_listing_sorting_id',
            'productStream.listing_sorting_uuid as __productStream_listing_sorting_uuid',
        ];
    }

    public function addProductStreamTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getAlbumFields(): array
    {
        return [
            'album.uuid as __array_key',
            'album.uuid as __album_uuid',
            'album.id as __album_id',
            'album.name as __album_name',
            'album.parent_uuid as __album_parent_uuid',
            'album.parent_id as __album_parent_id',
            'album.position as __album_position',
            'album.create_thumbnails as __album_create_thumbnails',
            'album.thumbnail_size as __album_thumbnail_size',
            'album.icon as __album_icon',
            'album.thumbnail_high_dpi as __album_thumbnail_high_dpi',
            'album.thumbnail_quality as __album_thumbnail_quality',
            'album.thumbnail_high_dpi_quality as __album_thumbnail_high_dpi_quality',
        ];
    }

    public function addAlbumTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getAreaFields(): array
    {
        return [
            'area.uuid as __array_key',
            'area.id as __area_id',
            'area.uuid as __area_uuid',
            'area.name as __area_name',
            'area.active as __area_active',
        ];
    }

    public function addAreaTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getAreaCountryFields(): array
    {
        return [
            'areaCountry.uuid as __array_key',
            'areaCountry.name as __areaCountry_name',
            'areaCountry.iso as __areaCountry_iso',
            'areaCountry.en as __areaCountry_en',
            'areaCountry.position as __areaCountry_position',
            'areaCountry.notice as __areaCountry_notice',
            'areaCountry.shipping_free as __areaCountry_shipping_free',
            'areaCountry.tax_free as __areaCountry_tax_free',
            'areaCountry.taxfree_for_vat_id as __areaCountry_taxfree_for_vat_id',
            'areaCountry.taxfree_vatid_checked as __areaCountry_taxfree_vatid_checked',
            'areaCountry.active as __areaCountry_active',
            'areaCountry.iso3 as __areaCountry_iso3',
            'areaCountry.display_state_in_registration as __areaCountry_display_state_in_registration',
            'areaCountry.force_state_in_registration as __areaCountry_force_state_in_registration',
            'areaCountry.uuid as __areaCountry_uuid',
            'areaCountry.area_uuid as __areaCountry_area_uuid',
        ];
    }

    public function addAreaCountryTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getAreaCountryStateFields(): array
    {
        return [
            'areaCountryState.uuid as __array_key',
            'areaCountryState.uuid as __areaCountryState_uuid',
            'areaCountryState.area_country_uuid as __areaCountryState_area_country_uuid',
            'areaCountryState.name as __areaCountryState_name',
            'areaCountryState.short_code as __areaCountryState_short_code',
            'areaCountryState.position as __areaCountryState_position',
            'areaCountryState.active as __areaCountryState_active',
        ];
    }

    public function addAreaCountryStateTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getCategoryFields(): array
    {
        return [
            'category.uuid as __array_key',
            'category.uuid as __category_uuid',
            'category.parent_uuid as __category_parent_uuid',
            'category.path as __category_path',
            'category.description as __category_description',
            'category.position as __category_position',
            'category.level as __category_level',
            'category.added as __category_added',
            'category.changed_at as __category_changed_at',
            'category.meta_keywords as __category_meta_keywords',
            'category.meta_title as __category_meta_title',
            'category.meta_description as __category_meta_description',
            'category.cms_headline as __category_cms_headline',
            'category.cms_description as __category_cms_description',
            'category.template as __category_template',
            'category.active as __category_active',
            'category.is_blog as __category_is_blog',
            'category.external as __category_external',
            'category.hide_filter as __category_hide_filter',
            'category.hide_top as __category_hide_top',
            'category.media_uuid as __category_media_uuid',
            'category.product_box_layout as __category_product_box_layout',
            'category.stream_id as __category_stream_id',
            'category.hide_sortings as __category_hide_sortings',
            'category.sorting_ids as __category_sorting_ids',
            'category.facet_ids as __category_facet_ids',
        ];
    }

    public function addCategoryTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getCurrencyFields(): array
    {
        return [
            'currency.uuid as __array_key',
            'currency.uuid as __currency_uuid',
            'currency.currency as __currency_currency',
            'currency.name as __currency_name',
            'currency.standard as __currency_standard',
            'currency.factor as __currency_factor',
            'currency.template_char as __currency_template_char',
            'currency.symbol_position as __currency_symbol_position',
            'currency.position as __currency_position',
        ];
    }

    public function addCurrencyTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getCustomerFields(): array
    {
        return [
            'customer.uuid as __array_key',
            'customer.id as __customer_id',
            'customer.uuid as __customer_uuid',
            'customer.password as __customer_password',
            'customer.encoder as __customer_encoder',
            'customer.email as __customer_email',
            'customer.active as __customer_active',
            'customer.account_mode as __customer_account_mode',
            'customer.confirmation_key as __customer_confirmation_key',
            'customer.payment_method_id as __customer_payment_method_id',
            'customer.payment_method_uuid as __customer_payment_method_uuid',
            'customer.first_login as __customer_first_login',
            'customer.last_login as __customer_last_login',
            'customer.session_id as __customer_session_id',
            'customer.newsletter as __customer_newsletter',
            'customer.validation as __customer_validation',
            'customer.affiliate as __customer_affiliate',
            'customer.customer_group_key as __customer_customer_group_key',
            'customer.customer_group_uuid as __customer_customer_group_uuid',
            'customer.default_payment_method_id as __customer_default_payment_method_id',
            'customer.default_payment_method_uuid as __customer_default_payment_method_uuid',
            'customer.sub_shop_id as __customer_sub_shop_id',
            'customer.sub_shop_uuid as __customer_sub_shop_uuid',
            'customer.main_shop_id as __customer_main_shop_id',
            'customer.main_shop_uuid as __customer_main_shop_uuid',
            'customer.referer as __customer_referer',
            'customer.price_group_id as __customer_price_group_id',
            'customer.price_group_uuid as __customer_price_group_uuid',
            'customer.internal_comment as __customer_internal_comment',
            'customer.failed_logins as __customer_failed_logins',
            'customer.locked_until as __customer_locked_until',
            'customer.default_billing_address_id as __customer_default_billing_address_id',
            'customer.default_billing_address_uuid as __customer_default_billing_address_uuid',
            'customer.default_shipping_address_id as __customer_default_shipping_address_id',
            'customer.default_shipping_address_uuid as __customer_default_shipping_address_uuid',
            'customer.title as __customer_title',
            'customer.salutation as __customer_salutation',
            'customer.first_name as __customer_first_name',
            'customer.last_name as __customer_last_name',
            'customer.birthday as __customer_birthday',
            'customer.customer_number as __customer_customer_number',
        ];
    }

    public function addCustomerTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getCustomerAddressFields(): array
    {
        return [
            'customerAddress.uuid as __array_key',
            'customerAddress.id as __customerAddress_id',
            'customerAddress.uuid as __customerAddress_uuid',
            'customerAddress.customer_id as __customerAddress_customer_id',
            'customerAddress.customer_uuid as __customerAddress_customer_uuid',
            'customerAddress.company as __customerAddress_company',
            'customerAddress.department as __customerAddress_department',
            'customerAddress.salutation as __customerAddress_salutation',
            'customerAddress.title as __customerAddress_title',
            'customerAddress.first_name as __customerAddress_first_name',
            'customerAddress.last_name as __customerAddress_last_name',
            'customerAddress.street as __customerAddress_street',
            'customerAddress.zipcode as __customerAddress_zipcode',
            'customerAddress.city as __customerAddress_city',
            'customerAddress.area_country_id as __customerAddress_area_country_id',
            'customerAddress.area_country_uuid as __customerAddress_area_country_uuid',
            'customerAddress.area_country_state_id as __customerAddress_area_country_state_id',
            'customerAddress.area_country_state_uuid as __customerAddress_area_country_state_uuid',
            'customerAddress.vat_id as __customerAddress_vat_id',
            'customerAddress.phone_number as __customerAddress_phone_number',
            'customerAddress.additional_address_line1 as __customerAddress_additional_address_line1',
            'customerAddress.additional_address_line2 as __customerAddress_additional_address_line2',
        ];
    }

    public function addCustomerAddressTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getCustomerGroupFields(): array
    {
        return [
            'customerGroup.uuid as __array_key',
            'customerGroup.uuid as __customerGroup_uuid',
            'customerGroup.group_key as __customerGroup_group_key',
            'customerGroup.description as __customerGroup_description',
            'customerGroup.display_gross_prices as __customerGroup_display_gross_prices',
            'customerGroup.input_gross_prices as __customerGroup_input_gross_prices',
            'customerGroup.mode as __customerGroup_mode',
            'customerGroup.discount as __customerGroup_discount',
            'customerGroup.minimum_order_amount as __customerGroup_minimum_order_amount',
            'customerGroup.minimum_order_amount_surcharge as __customerGroup_minimum_order_amount_surcharge',
        ];
    }

    public function addCustomerGroupTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getHolidayFields(): array
    {
        return [
            'holiday.uuid as __array_key',
            'holiday.id as __holiday_id',
            'holiday.uuid as __holiday_uuid',
            'holiday.name as __holiday_name',
            'holiday.calculation as __holiday_calculation',
            'holiday.date as __holiday_date',
        ];
    }

    public function addHolidayTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getLocaleFields(): array
    {
        return [
            'locale.uuid as __array_key',
            'locale.uuid as __locale_uuid',
            'locale.locale as __locale_locale',
            'locale.language as __locale_language',
            'locale.territory as __locale_territory',
        ];
    }

    public function addLocaleTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getMediaFields(): array
    {
        return [
            'media.uuid as __array_key',
            'media.uuid as __media_uuid',
            'media.album_uuid as __media_album_uuid',
            'media.name as __media_name',
            'media.description as __media_description',
            'media.file_name as __media_file_name',
            'media.mime_type as __media_mime_type',
            'media.file_size as __media_file_size',
            'media.meta_data as __media_meta_data',
            'media.created_at as __media_created_at',
            'media.user_uuid as __media_user_uuid',
            'media.id as __media_id',
            'media.album_id as __media_album_id',
            'media.user_id as __media_user_id',
            'media.updated_at as __media_updated_at',
        ];
    }

    public function addMediaTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getPaymentMethodFields(): array
    {
        return [
            'paymentMethod.uuid as __array_key',
            'paymentMethod.uuid as __paymentMethod_uuid',
            'paymentMethod.name as __paymentMethod_name',
            'paymentMethod.description as __paymentMethod_description',
            'paymentMethod.template as __paymentMethod_template',
            'paymentMethod.class as __paymentMethod_class',
            'paymentMethod.table as __paymentMethod_table',
            'paymentMethod.hide as __paymentMethod_hide',
            'paymentMethod.additional_description as __paymentMethod_additional_description',
            'paymentMethod.debit_percent as __paymentMethod_debit_percent',
            'paymentMethod.surcharge as __paymentMethod_surcharge',
            'paymentMethod.surcharge_string as __paymentMethod_surcharge_string',
            'paymentMethod.position as __paymentMethod_position',
            'paymentMethod.active as __paymentMethod_active',
            'paymentMethod.allow_esd as __paymentMethod_allow_esd',
            'paymentMethod.used_iframe as __paymentMethod_used_iframe',
            'paymentMethod.hide_prospect as __paymentMethod_hide_prospect',
            'paymentMethod.action as __paymentMethod_action',
            'paymentMethod.plugin_uuid as __paymentMethod_plugin_uuid',
            'paymentMethod.source as __paymentMethod_source',
            'paymentMethod.mobile_inactive as __paymentMethod_mobile_inactive',
            'paymentMethod.risk_rules as __paymentMethod_risk_rules',
        ];
    }

    public function addPaymentMethodTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getPriceGroupFields(): array
    {
        return [
            'priceGroup.uuid as __array_key',
            'priceGroup.id as __priceGroup_id',
            'priceGroup.uuid as __priceGroup_uuid',
            'priceGroup.description as __priceGroup_description',
        ];
    }

    public function addPriceGroupTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getPriceGroupDiscountFields(): array
    {
        return [
            'priceGroupDiscount.uuid as __array_key',
            'priceGroupDiscount.uuid as __priceGroupDiscount_uuid',
            'priceGroupDiscount.price_group_uuid as __priceGroupDiscount_price_group_uuid',
            'priceGroupDiscount.customer_group_uuid as __priceGroupDiscount_customer_group_uuid',
            'priceGroupDiscount.discount as __priceGroupDiscount_discount',
            'priceGroupDiscount.discount_start as __priceGroupDiscount_discount_start',
        ];
    }

    public function addPriceGroupDiscountTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getShippingMethodFields(): array
    {
        return [
            'shippingMethod.uuid as __array_key',
            'shippingMethod.id as __shippingMethod_id',
            'shippingMethod.uuid as __shippingMethod_uuid',
            'shippingMethod.name as __shippingMethod_name',
            'shippingMethod.type as __shippingMethod_type',
            'shippingMethod.description as __shippingMethod_description',
            'shippingMethod.comment as __shippingMethod_comment',
            'shippingMethod.active as __shippingMethod_active',
            'shippingMethod.position as __shippingMethod_position',
            'shippingMethod.calculation as __shippingMethod_calculation',
            'shippingMethod.surcharge_calculation as __shippingMethod_surcharge_calculation',
            'shippingMethod.tax_calculation as __shippingMethod_tax_calculation',
            'shippingMethod.shipping_free as __shippingMethod_shipping_free',
            'shippingMethod.shop_id as __shippingMethod_shop_id',
            'shippingMethod.shop_uuid as __shippingMethod_shop_uuid',
            'shippingMethod.customer_group_id as __shippingMethod_customer_group_id',
            'shippingMethod.customer_group_uuid as __shippingMethod_customer_group_uuid',
            'shippingMethod.bind_shippingfree as __shippingMethod_bind_shippingfree',
            'shippingMethod.bind_time_from as __shippingMethod_bind_time_from',
            'shippingMethod.bind_time_to as __shippingMethod_bind_time_to',
            'shippingMethod.bind_instock as __shippingMethod_bind_instock',
            'shippingMethod.bind_laststock as __shippingMethod_bind_laststock',
            'shippingMethod.bind_weekday_from as __shippingMethod_bind_weekday_from',
            'shippingMethod.bind_weekday_to as __shippingMethod_bind_weekday_to',
            'shippingMethod.bind_weight_from as __shippingMethod_bind_weight_from',
            'shippingMethod.bind_weight_to as __shippingMethod_bind_weight_to',
            'shippingMethod.bind_price_from as __shippingMethod_bind_price_from',
            'shippingMethod.bind_price_to as __shippingMethod_bind_price_to',
            'shippingMethod.bind_sql as __shippingMethod_bind_sql',
            'shippingMethod.status_link as __shippingMethod_status_link',
            'shippingMethod.calculation_sql as __shippingMethod_calculation_sql',
        ];
    }

    public function addShippingMethodTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getShippingMethodPriceFields(): array
    {
        return [
            'shippingMethodPrice.uuid as __array_key',
            'shippingMethodPrice.id as __shippingMethodPrice_id',
            'shippingMethodPrice.uuid as __shippingMethodPrice_uuid',
            'shippingMethodPrice.from as __shippingMethodPrice_from',
            'shippingMethodPrice.value as __shippingMethodPrice_value',
            'shippingMethodPrice.factor as __shippingMethodPrice_factor',
            'shippingMethodPrice.shipping_method_id as __shippingMethodPrice_shipping_method_id',
            'shippingMethodPrice.shipping_method_uuid as __shippingMethodPrice_shipping_method_uuid',
        ];
    }

    public function addShippingMethodPriceTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getShopFields(): array
    {
        return [
            'shop.uuid as __array_key',
            'shop.id as __shop_id',
            'shop.uuid as __shop_uuid',
            'shop.main_id as __shop_main_id',
            'shop.name as __shop_name',
            'shop.title as __shop_title',
            'shop.position as __shop_position',
            'shop.host as __shop_host',
            'shop.base_path as __shop_base_path',
            'shop.base_url as __shop_base_url',
            'shop.hosts as __shop_hosts',
            'shop.secure as __shop_secure',
            'shop.shop_template_id as __shop_shop_template_id',
            'shop.document_template_id as __shop_document_template_id',
            'shop.category_id as __shop_category_id',
            'shop.locale_id as __shop_locale_id',
            'shop.currency_id as __shop_currency_id',
            'shop.customer_group_id as __shop_customer_group_id',
            'shop.fallback_id as __shop_fallback_id',
            'shop.customer_scope as __shop_customer_scope',
            'shop.is_default as __shop_is_default',
            'shop.active as __shop_active',
            'shop.payment_method_id as __shop_payment_method_id',
            'shop.shipping_method_id as __shop_shipping_method_id',
            'shop.area_country_id as __shop_area_country_id',
            'shop.tax_calculation_type as __shop_tax_calculation_type',
            'shop.main_uuid as __shop_main_uuid',
            'shop.shop_template_uuid as __shop_shop_template_uuid',
            'shop.document_template_uuid as __shop_document_template_uuid',
            'shop.category_uuid as __shop_category_uuid',
            'shop.locale_uuid as __shop_locale_uuid',
            'shop.currency_uuid as __shop_currency_uuid',
            'shop.customer_group_uuid as __shop_customer_group_uuid',
            'shop.fallback_locale_uuid as __shop_fallback_locale_uuid',
            'shop.payment_method_uuid as __shop_payment_method_uuid',
            'shop.shipping_method_uuid as __shop_shipping_method_uuid',
            'shop.area_country_uuid as __shop_area_country_uuid',
        ];
    }

    public function addShopTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getShopTemplateFields(): array
    {
        return [
            'shopTemplate.uuid as __array_key',
            'shopTemplate.id as __shopTemplate_id',
            'shopTemplate.uuid as __shopTemplate_uuid',
            'shopTemplate.template as __shopTemplate_template',
            'shopTemplate.name as __shopTemplate_name',
            'shopTemplate.description as __shopTemplate_description',
            'shopTemplate.author as __shopTemplate_author',
            'shopTemplate.license as __shopTemplate_license',
            'shopTemplate.esi as __shopTemplate_esi',
            'shopTemplate.style_support as __shopTemplate_style_support',
            'shopTemplate.emotion as __shopTemplate_emotion',
            'shopTemplate.version as __shopTemplate_version',
            'shopTemplate.plugin_id as __shopTemplate_plugin_id',
            'shopTemplate.plugin_uuid as __shopTemplate_plugin_uuid',
            'shopTemplate.parent_id as __shopTemplate_parent_id',
            'shopTemplate.parent_uuid as __shopTemplate_parent_uuid',
        ];
    }

    public function addShopTemplateTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getTaxFields(): array
    {
        return [
            'tax.uuid as __array_key',
            'tax.id as __tax_id',
            'tax.uuid as __tax_uuid',
            'tax.tax_rate as __tax_tax_rate',
            'tax.description as __tax_description',
        ];
    }

    public function addTaxTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getTaxAreaRuleFields(): array
    {
        return [
            'taxAreaRule.uuid as __array_key',
            'taxAreaRule.id as __taxAreaRule_id',
            'taxAreaRule.uuid as __taxAreaRule_uuid',
            'taxAreaRule.area_id as __taxAreaRule_area_id',
            'taxAreaRule.area_uuid as __taxAreaRule_area_uuid',
            'taxAreaRule.area_country_id as __taxAreaRule_area_country_id',
            'taxAreaRule.area_country_uuid as __taxAreaRule_area_country_uuid',
            'taxAreaRule.area_country_state_id as __taxAreaRule_area_country_state_id',
            'taxAreaRule.area_country_state_uuid as __taxAreaRule_area_country_state_uuid',
            'taxAreaRule.tax_id as __taxAreaRule_tax_id',
            'taxAreaRule.tax_uuid as __taxAreaRule_tax_uuid',
            'taxAreaRule.customer_group_id as __taxAreaRule_customer_group_id',
            'taxAreaRule.customer_group_uuid as __taxAreaRule_customer_group_uuid',
            'taxAreaRule.tax_rate as __taxAreaRule_tax_rate',
            'taxAreaRule.name as __taxAreaRule_name',
            'taxAreaRule.active as __taxAreaRule_active',
        ];
    }

    public function addTaxAreaRuleTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    public function getUnitFields(): array
    {
        return [
            'unit.uuid as __array_key',
            'unit.id as __unit_id',
            'unit.uuid as __unit_uuid',
            'unit.unit as __unit_unit',
            'unit.description as __unit_description',
        ];
    }

    public function addUnitTranslation(QueryBuilder $query, TranslationContext $context): void
    {
    }

    //#next fields
}
