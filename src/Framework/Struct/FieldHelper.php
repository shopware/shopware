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

namespace Shopware\Framework\Struct;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Context\Struct\TranslationContext;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class FieldHelper
{
    /**
     * Contains the selection for the s_articles_attributes table.
     * This table contains dynamically columns.
     *
     * @var array
     */
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

    /**
     * Helper function which generates an array with table column selections
     * for the passed table.
     *
     * @param string $table
     * @param string $alias
     *
     * @return array
     */
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

    /**
     * Defines which s_articles fields should be selected.
     *
     * @return array
     */
    public function getArticleFields(): array
    {
        $fields = [
            'product.uuid as __product_uuid',
            'product.product_manufacturer_uuid as __product_product_manufacturer_uuid',
            'product.name as __product_name',
            'product.description as __product_description',
            'product.description_long as __product_description_long',
            'product.shipping_time as __product_shipping_time',
            'product.created_at as __product_created_at',
            'product.active as __product_active',
            'product.tax_uuid as __product_tax_uuid',
            'product.pseudo_sales as __product_pseudo_sales',
            'product.topseller as __product_topseller',
            'product.meta_title as __product_meta_title',
            'product.keywords as __product_keywords',
            'product.updated_at as __product_updated_at',
            'product.price_group_id as __product_price_group_id',
            'product.filter_group_uuid as __product_filter_group_uuid',
            'product.last_stock as __product_last_stock',
            'product.crossbundlelook as __product_crossbundlelook',
            'product.notification as __product_notification',
            'product.template as __product_template',
            'product.mode as __product_mode',
            'product.main_detail_uuid as __product_main_detail_uuid',
            'product.available_from as __product_available_from',
            'product.available_to as __product_available_to',
            'product.configurator_set_id as __product_configurator_set_id',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('product_attribute', 'productAttribute')
        );

        return $fields;
    }

    /**
     * @return array
     */
    public function getTopSellerFields(): array
    {
        return [
            'topSeller.sales as __topSeller_sales',
        ];
    }

    /**
     * Defines which s_articles_details fields should be selected.
     *
     * @return array
     */
    public function getVariantFields(): array
    {
        return [
            'variant.uuid as __variant_uuid',
            'variant.order_number as __variant_order_number',
            'variant.supplier_number as __variant_supplier_number',
            'variant.kind as __variant_kind',
            'variant.additional_text as __variant_additional_text',
            'variant.sales as __variant_sales',
            'variant.active as __variant_active',
            'variant.stock as __variant_stock',
            'variant.stockmin as __variant_stockmin',
            'variant.weight as __variant_weight',
            'variant.position as __variant_position',
            'variant.width as __variant_width',
            'variant.height as __variant_height',
            'variant.length as __variant_length',
            'variant.ean as __variant_ean',
            'variant.unit_id as __variant_unit_id',
            'variant.release_date as __variant_release_date',
            'variant.shipping_free as __variant_shipping_free',
            'variant.shipping_time as __variant_shipping_time',
        ];
    }

    /**
     * @return array
     */
    public function getEsdFields(): array
    {
        $fields = [
            'esd.id as __esd_id',
            'esd.product_id as __esd_product_id',
            'esd.product_detail_id as __esd_product_detail_id',
            'esd.file as __esd_file',
            'esd.serials as __esd_serials',
            'esd.notification as __esd_notification',
            'esd.max_downloads as __esd_max_downloads',
            'esd.created_at as __esd_created_at',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('product_esd_attribute', 'esdAttribute')
        );

        return $fields;
    }

    /**
     * Defines which s_core_tax fields should be selected
     *
     * @return array
     */
    public function getTaxFields(): array
    {
        return [
            'tax.id as __tax_id',
            'tax.tax as __tax_tax',
            'tax.description as __tax_description',
        ];
    }

    /**
     * Defines which s_core_pricegroups fields should be selected
     *
     * @return array
     */
    public function getPriceGroupFields(): array
    {
        return [
            'priceGroup.id as __priceGroup_id',
            'priceGroup.description as __priceGroup_description',
        ];
    }

    /**
     * Defines which s_articles_suppliers fields should be selected
     *
     * @return array
     */
    public function getManufacturerFields(): array
    {
        $fields = [
            'manufacturer.id as __manufacturer_id',
            'manufacturer.name as __manufacturer_name',
            'manufacturer.img as __manufacturer_img',
            'manufacturer.link as __manufacturer_link',
            'manufacturer.description as __manufacturer_description',
            'manufacturer.meta_title as __manufacturer_meta_title',
            'manufacturer.meta_description as __manufacturer_meta_description',
            'manufacturer.meta_keywords as __manufacturer_meta_keywords',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('product_manufacturer_attribute', 'manufacturerAttribute')
        );

        return $fields;
    }

    /**
     * @return array
     */
    public function getCategoryFields(): array
    {
        $fields = [
            'category.id as array_key',
            'category.id as __category_id',
            'category.uuid as __category_uuid',
            'category.parent as __category_parent',
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
            'category.blog as __category_blog',
            'category.external as __category_external',
            'category.hide_filter as __category_hide_filter',
            'category.hide_top as __category_hide_top',
            'category.media_id as __category_media_id',
            'category.media_uuid as __category_media_uuid',
            'category.product_box_layout as __category_product_box_layout',
            'category.stream_id as __category_stream_id',
            'category.hide_sortings as __category_hide_sortings',
            'category.sorting_ids as __category_sorting_ids',
            'category.facet_ids as __category_facet_ids',
            'shop.id as __category_is_shop_category'
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('category_attribute', 'categoryAttribute')
        );

        return $fields;
    }

    /**
     * @return array
     */
    public function getPriceFields(): array
    {
        $fields = [
            'price.id as __price_id',
            'price.pricegroup as __price_pricegroup',
            'price.from as __price_from',
            'price.to as __price_to',
            'price.articleID as __price_articleID',
            'price.articledetailsID as __price_articledetailsID',
            'price.price as __price_price',
            'price.pseudoprice as __price_pseudoprice',
            'price.percent as __price_percent',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('product_price_attributes', 'priceAttribute')
        );

        return $fields;
    }

    /**
     * @return array
     */
    public function getUnitFields(): array
    {
        return [
            'unit.id as __unit_id',
            'unit.description as __unit_description',
            'unit.unit as __unit_unit',
            'variant.pack_unit as __unit_pack_unit',
            'variant.purchase_unit as __unit_purchase_unit',
            'variant.reference_unit as __unit_reference_unit',
            'variant.purchase_steps as __unit_purchase_steps',
            'variant.min_purchase as __unit_min_purchase',
            'variant.max_purchase as __unit_max_purchase',
        ];
    }

    /**
     * @return array
     */
    public function getConfiguratorSetFields(): array
    {
        return [
            'configuratorSet.id as __configuratorSet_id',
            'configuratorSet.name as __configuratorSet_name',
            'configuratorSet.type as __configuratorSet_type',
        ];
    }

    /**
     * @return array
     */
    public function getConfiguratorGroupFields(): array
    {
        $fields = [
            'configuratorGroup.id as __configuratorGroup_id',
            'configuratorGroup.name as __configuratorGroup_name',
            'configuratorGroup.description as __configuratorGroup_description',
            'configuratorGroup.position as __configuratorGroup_position',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_article_configurator_groups_attributes', 'configuratorGroupAttribute')
        );

        return $fields;
    }

    /**
     * @return array
     */
    public function getConfiguratorOptionFields(): array
    {
        $fields = [
            'configuratorOption.id as __configuratorOption_id',
            'configuratorOption.name as __configuratorOption_name',
            'configuratorOption.position as __configuratorOption_position',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_article_configurator_options_attributes', 'configuratorOptionAttribute')
        );

        return $fields;
    }

    /**
     * @return array
     */
    public function getCountryAreaFields(): array
    {
        return [
            'countryArea.id as __countryArea_id',
            'countryArea.name as __countryArea_name',
            'countryArea.active as __countryArea_active',
        ];
    }

    /**
     * @return array
     */
    public function getCountryFields(): array
    {
        $fields = [
            'country.id as __country_id',
            'country.countryname as __country_countryname',
            'country.countryiso as __country_countryiso',
            'country.areaID as __country_areaID',
            'country.countryen as __country_countryen',
            'country.position as __country_position',
            'country.notice as __country_notice',
            'country.shippingfree as __country_shippingfree',
            'country.taxfree as __country_taxfree',
            'country.taxfree_ustid as __country_taxfree_ustid',
            'country.taxfree_ustid_checked as __country_taxfree_ustid_checked',
            'country.active as __country_active',
            'country.iso3 as __country_iso3',
            'country.display_state_in_registration as __country_display_state_in_registration',
            'country.force_state_in_registration as __country_force_state_in_registration',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_core_countries_attributes', 'countryAttribute')
        );

        return $fields;
    }

    /**
     * @return array
     */
    public function getCountryStateFields(): array
    {
        $fields = [
            'countryState.id as __countryState_id',
            'countryState.countryID as __countryState_countryID',
            'countryState.name as __countryState_name',
            'countryState.shortcode as __countryState_shortcode',
            'countryState.position as __countryState_position',
            'countryState.active as __countryState_active',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_core_countries_states_attributes', 'countryStateAttribute')
        );

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getCustomerGroupFields(): array
    {
        $fields = [
            'customerGroup.id as __customerGroup_id',
            'customerGroup.groupkey as __customerGroup_groupkey',
            'customerGroup.description as __customerGroup_description',
            'customerGroup.tax as __customerGroup_tax',
            'customerGroup.taxinput as __customerGroup_taxinput',
            'customerGroup.mode as __customerGroup_mode',
            'customerGroup.discount as __customerGroup_discount',
            'customerGroup.minimumorder as __customerGroup_minimumorder',
            'customerGroup.minimumordersurcharge as __customerGroup_minimumordersurcharge',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_core_customergroups_attributes', 'customerGroupAttribute')
        );

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getDownloadFields(): array
    {
        $fields = [
            'download.id as __download_id',
            'download.articleID as __download_articleID',
            'download.description as __download_description',
            'download.filename as __download_filename',
            'download.size as __download_size',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_articles_downloads_attributes', 'downloadAttribute')
        );

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getLinkFields(): array
    {
        $fields = [
            'link.id as __link_id',
            'link.articleID as __link_articleID',
            'link.description as __link_description',
            'link.link as __link_link',
            'link.target as __link_target',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_articles_information_attributes', 'linkAttribute')
        );

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getImageFields(): array
    {
        $fields = [
            'image.id as __image_id',
            'image.articleID as __image_articleID',
            'image.img as __image_img',
            'image.main as __image_main',
            'image.description as __image_description',
            'image.position as __image_position',
            'image.width as __image_width',
            'image.height as __image_height',
            'image.extension as __image_extension',
            'image.parent_id as __image_parent_id',
            'image.media_id as __image_media_id',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_articles_img_attributes', 'imageAttribute')
        );

        return $fields;
    }

    /**
     * Returns an array with all required media fields for a full media selection.
     * Requires that the s_media table is included with table alias 'media'
     *
     * @return array
     */
    public function getMediaFields(): array
    {
        $fields = [
            'media.id as __media_id',
            'media.albumID as __media_albumID',
            'media.name as __media_name',
            'media.description as __media_description',
            'media.path as __media_path',
            'media.type as __media_type',
            'media.extension as __media_extension',
            'media.file_size as __media_file_size',
            'media.width as __media_width',
            'media.height as __media_height',
            'media.userID as __media_userID',
            'media.created as __media_created',
            'mediaSettings.id as __mediaSettings_id',
            'mediaSettings.create_thumbnails as __mediaSettings_create_thumbnails',
            'mediaSettings.thumbnail_size as __mediaSettings_thumbnail_size',
            'mediaSettings.icon as __mediaSettings_icon',
            'mediaSettings.thumbnail_high_dpi as __mediaSettings_thumbnail_high_dpi',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_media_attributes', 'mediaAttribute')
        );

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getPriceGroupDiscountFields(): array
    {
        return [
            'priceGroupDiscount.id as __priceGroupDiscount_id',
            'priceGroupDiscount.groupID as __priceGroupDiscount_groupID',
            'priceGroupDiscount.discount as __priceGroupDiscount_discount',
            'priceGroupDiscount.discountstart as __priceGroupDiscount_discountstart',
        ];
    }

    /**
     * @return string[]
     */
    public function getPropertySetFields(): array
    {
        $fields = [
            'propertySet.id as __propertySet_id',
            'propertySet.name as __propertySet_name',
            'propertySet.position as __propertySet_position',
            'propertySet.comparable as __propertySet_comparable',
            'propertySet.sortmode as __propertySet_sortmode',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_filter_attributes', 'propertySetAttribute')
        );

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getPropertyGroupFields(): array
    {
        $fields = [
            'propertyGroup.id as __propertyGroup_id',
            'propertyGroup.name as __propertyGroup_name',
            'propertyGroup.filterable as __propertyGroup_filterable',
        ];
        $fields = array_merge(
            $fields,
            $this->getTableFields('s_filter_options_attributes', 'propertyGroupAttribute')
        );

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getPropertyOptionFields(): array
    {
        $fields = [
            'propertyOption.id as __propertyOption_id',
            'propertyOption.optionID as __propertyOption_optionID',
            'propertyOption.value as __propertyOption_value',
            'propertyOption.position as __propertyOption_position',
        ];
        $fields = array_merge(
            $fields,
            $this->getTableFields('s_filter_values_attributes', 'propertyOptionAttribute')
        );

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getTaxRuleFields(): array
    {
        return [
            'taxRule.groupID as __taxRule_groupID',
            'taxRule.tax as __taxRule_tax',
            'taxRule.name as __taxRule_name',
        ];
    }

    /**
     * @return string[]
     */
    public function getVoteFields(): array
    {
        return [
            'vote.id as __vote_id',
            'vote.articleID as __vote_articleID',
            'vote.name as __vote_name',
            'vote.headline as __vote_headline',
            'vote.comment as __vote_comment',
            'vote.points as __vote_points',
            'vote.datum as __vote_datum',
            'vote.active as __vote_active',
            'vote.email as __vote_email',
            'vote.answer as __vote_answer',
            'vote.answer_date as __vote_answer_date',
        ];
    }

    public function getShopFields(): array
    {
        return [
            'shop.id as array_key',
            'shop.id as __shop_id',
            'shop.uuid as __shop_uuid',
            'shop.main_id as __shop_main_id',
            'shop.name as __shop_name',
            'shop.title as __shop_title',
            'shop.position as __shop_position',
            'shop.position as __shop_position',
            'shop.base_url as __shop_base_url',
            'shop.category_id as __shop_category_id',
            'shop.locale_id as __shop_locale_id',
            'shop.currency_id as __shop_currency_id',
            'shop.customer_group_id as __shop_customer_group_id',
            'shop.fallback_id as __shop_fallback_id',
            'shop.customer_scope as __shop_customer_scope',
            'shop.default as __shop_default',
            'shop.active as __shop_active',
            //fields which considers sub shop inheritance
            'main.host as __shop_host',
            'main.hosts as __shop_hosts',
            'main.base_path as __shop_base_path',
            'main.secure as __shop_secure',
            'main.template_id as __shop_template_id',
            'main.document_template_id as __shop_document_template_id',
            'main.payment_id as __shop_payment_id',
            'main.dispatch_id as __shop_dispatch_id',
            'main.country_id as __shop_country_id',
            'main.tax_calculation_type as __shop_tax_calculation_type',
        ];
    }

    public function getCurrencyFields(): array
    {
        return [
            'currency.id as __currency_id',
            'currency.currency as __currency_currency',
            'currency.name as __currency_name',
            'currency.standard as __currency_standard',
            'currency.factor as __currency_factor',
            'currency.templatechar as __currency_templatechar',
            'currency.symbol_position as __currency_symbol_position',
            'currency.position as __currency_position',
        ];
    }

    public function getTemplateFields(): array
    {
        return [
            'template.id as __template_id',
            'template.template as __template_template',
            'template.name as __template_name',
            'template.description as __template_description',
            'template.author as __template_author',
            'template.license as __template_license',
            'template.esi as __template_esi',
            'template.style_support as __template_style_support',
            'template.emotion as __template_emotion',
            'template.version as __template_version',
            'template.plugin_id as __template_plugin_id',
            'template.parent_id as __template_parent_id',
        ];
    }

    public function getLocaleFields(): array
    {
        return [
            'locale.id as __locale_id',
            'locale.locale as __locale_locale',
            'locale.language as __locale_language',
            'locale.territory as __locale_territory',
        ];
    }

    /**
     * Returns an array with all required related product stream fields.
     * Requires that the s_product_streams table is included with table alias 'stream'
     *
     * @return array
     */
    public function getRelatedProductStreamFields(): array
    {
        $fields = [
            'stream.id as __stream_id',
            'stream.name as __stream_name',
            'stream.description as __stream_description',
            'stream.type as __stream_type',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_product_streams_attributes', 'productStreamAttribute')
        );

        return $fields;
    }

    /**
     * Returns an array with all required shop page fields.
     * Requires that the s_cms_static table is included with table alias 'page'
     *
     * @return array
     */
    public function getShopPageFields(): array
    {
        $fields = [
            'page.id as __page_id',
            'page.tpl1variable as __page_tpl1variable',
            'page.tpl1path as __page_tpl1path',
            'page.tpl2variable as __page_tpl2variable',
            'page.tpl2path as __page_tpl2path',
            'page.tpl3variable as __page_tpl3variable',
            'page.tpl3path as __page_tpl3path',
            'page.description as __page_description',
            'page.html as __page_html',
            'page.grouping as __page_grouping',
            'page.position as __page_position',
            'page.link as __page_link',
            'page.target as __page_target',
            'page.parentID as __page_parent_id',
            'page.page_title as __page_page_title',
            'page.meta_keywords as __page_meta_keywords',
            'page.meta_description as __page_meta_description',
            'page.changed as __page_changed',
            'page.shop_ids as __page_shop_ids',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_cms_static_attributes', 'pageAttribute')
        );

        return $fields;
    }

    public function getCustomerFields(): array
    {
        $fields = [
            'customer.id as __customer_id',
            'customer.email as __customer_email',
            'customer.active as __customer_active',
            'customer.accountmode as __customer_accountmode',
            'customer.confirmationkey as __customer_confirmationkey',
            'customer.paymentID as __customer_paymentID',
            'customer.firstlogin as __customer_firstlogin',
            'customer.lastlogin as __customer_lastlogin',
            'customer.newsletter as __customer_newsletter',
            'customer.validation as __customer_validation',
            'customer.affiliate as __customer_affiliate',
            'customer.customergroup as __customer_customergroup',
            'customer.paymentpreset as __customer_paymentpreset',
            'customer.language as __customer_language',
            'customer.subshopID as __customer_subshopID',
            'customer.referer as __customer_referer',
            'customer.internalcomment as __customer_internalcomment',
            'customer.failedlogins as __customer_failedlogins',
            'customer.lockeduntil as __customer_lockeduntil',
            'customer.default_billing_address_id as __customer_default_billing_address_id',
            'customer.default_shipping_address_id as __customer_default_shipping_address_id',
            'customer.title as __customer_title',
            'customer.salutation as __customer_salutation',
            'customer.firstname as __customer_firstname',
            'customer.lastname as __customer_lastname',
            'customer.birthday as __customer_birthday',
            'customer.customernumber as __customer_customernumber',
        ];
        $fields = array_merge(
            $fields,
            $this->getTableFields('s_user_attributes', 'customerAttribute')
        );

        return $fields;
    }

    public function getShippingMethodFields(): array
    {
        $fields = [
            'shippingMethod.id as __shippingMethod_id',
            'shippingMethod.name as __shippingMethod_name',
            'shippingMethod.type as __shippingMethod_type',
            'shippingMethod.description as __shippingMethod_description',
            'shippingMethod.comment as __shippingMethod_comment',
            'shippingMethod.active as __shippingMethod_active',
            'shippingMethod.position as __shippingMethod_position',
            'shippingMethod.calculation as __shippingMethod_calculation',
            'shippingMethod.surcharge_calculation as __shippingMethod_surcharge_calculation',
            'shippingMethod.tax_calculation as __shippingMethod_tax_calculation',
            'shippingMethod.shippingfree as __shippingMethod_shippingfree',
            'shippingMethod.multishopID as __shippingMethod_multishopID',
            'shippingMethod.customergroupID as __shippingMethod_customergroupID',
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
            'shippingMethod.status_link as __shippingMethod_status_link',
        ];

        return array_merge($fields, $this->getTableFields('s_premium_dispatch_attributes', 'shippingMethodAttribute'));
    }

    public function getPaymentMethodFields(): array
    {
        $fields = [
            'paymentMethod.id as __paymentMethod_id',
            'paymentMethod.name as __paymentMethod_name',
            'paymentMethod.description as __paymentMethod_description',
            'paymentMethod.template as __paymentMethod_template',
            'paymentMethod.class as __paymentMethod_class',
            'paymentMethod.table as __paymentMethod_table',
            'paymentMethod.hide as __paymentMethod_hide',
            'paymentMethod.additionaldescription as __paymentMethod_additionaldescription',
            'paymentMethod.debit_percent as __paymentMethod_debit_percent',
            'paymentMethod.surcharge as __paymentMethod_surcharge',
            'paymentMethod.surchargestring as __paymentMethod_surchargestring',
            'paymentMethod.position as __paymentMethod_position',
            'paymentMethod.active as __paymentMethod_active',
            'paymentMethod.esdactive as __paymentMethod_esdactive',
            'paymentMethod.embediframe as __paymentMethod_embediframe',
            'paymentMethod.hideprospect as __paymentMethod_hideprospect',
            'paymentMethod.action as __paymentMethod_action',
            'paymentMethod.pluginID as __paymentMethod_pluginId',
            'paymentMethod.source as __paymentMethod_source',
            'paymentMethod.mobile_inactive as __paymentMethod_mobile_inactive',
            'paymentMethod.risk_rules as __paymentMethod_rules',
        ];

        return array_merge($fields, $this->getTableFields('s_core_paymentmeans_attributes', 'paymentMethodAttribute'));
    }

    public function getAddressFields(): array
    {
        $fields = [
            'address.id as __address_id',
            'address.user_id as __address_user_id',
            'address.company as __address_company',
            'address.department as __address_department',
            'address.salutation as __address_salutation',
            'address.title as __address_title',
            'address.firstname as __address_firstname',
            'address.lastname as __address_lastname',
            'address.street as __address_street',
            'address.zipcode as __address_zipcode',
            'address.city as __address_city',
            'address.country_id as __address_country_id',
            'address.state_id as __address_state_id',
            'address.ustid as __address_ustid',
            'address.phone as __address_phone',
            'address.additional_address_line1 as __address_additional_address_line1',
            'address.additional_address_line2 as __address_additional_address_line2',
        ];
        $fields = array_merge(
            $fields,
            $this->getTableFields('s_user_addresses_attributes', 'addressAttribute')
        );

        return $fields;
    }

    /**
     * Returns an array with all required emotion fields.
     * Requires that the s_emotion table is included with table alias 'emotion'
     *
     * @return array
     */
    public function getEmotionFields(): array
    {
        $fields = [
            'emotion.id AS __emotion_id',
            'emotion.active AS __emotion_active',
            'emotion.name AS __emotion_name',
            'emotion.cols AS __emotion_cols',
            'emotion.cell_spacing AS __emotion_cell_spacing',
            'emotion.cell_height AS __emotion_cell_height',
            'emotion.article_height AS __emotion_article_height',
            'emotion.rows AS __emotion_rows',
            'emotion.valid_from AS __emotion_valid_from',
            'emotion.valid_to AS __emotion_valid_to',
            'emotion.userID AS __emotion_user_id',
            'emotion.show_listing AS __emotion_show_listing',
            'emotion.is_landingpage AS __emotion_is_landingpage',
            'emotion.seo_title AS __emotion_seo_title',
            'emotion.seo_keywords AS __emotion_seo_keywords',
            'emotion.seo_description AS __emotion_seo_description',
            'emotion.create_date AS __emotion_create_date',
            'emotion.modified AS __emotion_modified',
            'emotion.template_id AS __emotion_template_id',
            'emotion.device AS __emotion_device',
            'emotion.fullscreen AS __emotion_fullscreen',
            'emotion.mode AS __emotion_mode',
            'emotion.position AS __emotion_position',
            'emotion.parent_id AS __emotion_parent_id',
            'emotion.preview_id AS __emotion_preview_id',
            'emotion.preview_secret AS __emotion_preview_secret',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_emotion_attributes', 'emotionAttribute')
        );

        return $fields;
    }

    /**
     * Returns an array with all required emotion fields.
     * Requires that the s_emotion_templates table is included with table alias 'emotionTemplate'
     *
     * @return array
     */
    public function getEmotionTemplateFields(): array
    {
        $fields = [
            'emotionTemplate.id AS __emotionTemplate_id',
            'emotionTemplate.name AS __emotionTemplate_name',
            'emotionTemplate.file AS __emotionTemplate_file',
        ];

        return $fields;
    }

    /**
     * Returns an array with all required emotion element fields.
     * Requires that the s_emotion_element table is included with table alias 'emotionElement'
     *
     * @return array
     */
    public function getEmotionElementFields(): array
    {
        $fields = [
            'emotionElement.id AS __emotionElement_id',
            'emotionElement.emotionID AS __emotionElement_emotion_id',
            'emotionElement.componentID AS __emotionElement_component_id',
            'emotionElement.start_row AS __emotionElement_start_row',
            'emotionElement.start_col AS __emotionElement_start_col',
            'emotionElement.end_row AS __emotionElement_end_row',
            'emotionElement.end_col AS __emotionElement_end_col',
            'emotionElement.css_class AS __emotionElement_css_class',
        ];

        return $fields;
    }

    /**
     * Returns an array with all required emotion element value fields.
     * Requires that the s_emotion_element_value table is included with table alias 'emotionElementValue'
     *
     * @return array
     */
    public function getEmotionElementValueFields(): array
    {
        $fields = [
            'emotionElementValue.id AS __emotionElementValue_id',
            'emotionElementValue.emotionID AS __emotionElementValue_emotion_id',
            'emotionElementValue.elementID AS __emotionElementValue_element_id',
            'emotionElementValue.componentID AS __emotionElementValue_component_id',
            'emotionElementValue.fieldID AS __emotionElementValue_field_id',
            'emotionElementValue.value AS __emotionElementValue_value',
        ];

        return $fields;
    }

    /**
     * Returns an array with all required emotion component fields.
     * Requires that the s_library_component table is included with table alias 'emotionLibraryComponent'
     *
     * @return array
     */
    public function getEmotionElementLibraryFields(): array
    {
        $fields = [
            'emotionLibraryComponent.id AS __emotionLibraryComponent_id',
            'emotionLibraryComponent.name AS __emotionLibraryComponent_name',
            'emotionLibraryComponent.x_type AS __emotionLibraryComponent_x_type',
            'emotionLibraryComponent.convert_function AS __emotionLibraryComponent_convert_function',
            'emotionLibraryComponent.description AS __emotionLibraryComponent_description',
            'emotionLibraryComponent.template AS __emotionLibraryComponent_template',
            'emotionLibraryComponent.cls AS __emotionLibraryComponent_cls',
            'emotionLibraryComponent.pluginID AS __emotionLibraryComponent_plugin_id',
        ];

        return $fields;
    }

    /**
     * Returns an array with all required emotion component settings fields.
     * Requires that the s_library_component_fields table is included with table alias 'emotionLibraryComponentField'
     *
     * @return array
     */
    public function getEmotionElementLibraryFieldFields(): array
    {
        $fields = [
            'emotionLibraryComponentField.id AS __emotionLibraryComponentField_id',
            'emotionLibraryComponentField.componentID AS __emotionLibraryComponentField_component_id',
            'emotionLibraryComponentField.name AS __emotionLibraryComponentField_name',
            'emotionLibraryComponentField.x_type AS __emotionLibraryComponentField_x_type',
            'emotionLibraryComponentField.value_type AS __emotionLibraryComponentField_value_type',
            'emotionLibraryComponentField.field_label AS __emotionLibraryComponentField_field_label',
            'emotionLibraryComponentField.support_text AS __emotionLibraryComponentField_support_text',
            'emotionLibraryComponentField.help_title AS __emotionLibraryComponentField_help_title',
            'emotionLibraryComponentField.help_text AS __emotionLibraryComponentField_help_text',
            'emotionLibraryComponentField.store AS __emotionLibraryComponentField_store',
            'emotionLibraryComponentField.display_field AS __emotionLibraryComponentField_display_field',
            'emotionLibraryComponentField.value_field AS __emotionLibraryComponentField_value_field',
            'emotionLibraryComponentField.default_value AS __emotionLibraryComponentField_default_value',
            'emotionLibraryComponentField.allow_blank AS __emotionLibraryComponentField_allow_blank',
            'emotionLibraryComponentField.translatable AS __emotionLibraryComponentField_translatable',
            'emotionLibraryComponentField.position AS __emotionLibraryComponentField_position',
        ];

        return $fields;
    }

    /**
     * Returns an array with all required emotion element viewport fields.
     * Requires that the s_emotion_element_viewport table is included with table alias 'emotionElementViewport'
     *
     * @return array
     */
    public function getEmotionElementViewportFields(): array
    {
        $fields = [
            'emotionElementViewport.id AS __emotionElementViewport_id',
            'emotionElementViewport.emotionID AS __emotionElementViewport_emotion_id',
            'emotionElementViewport.elementID AS __emotionElementViewport_element_id',
            'emotionElementViewport.alias AS __emotionElementViewport_alias',
            'emotionElementViewport.start_row AS __emotionElementViewport_start_row',
            'emotionElementViewport.start_col AS __emotionElementViewport_start_col',
            'emotionElementViewport.end_row AS __emotionElementViewport_end_row',
            'emotionElementViewport.end_col AS __emotionElementViewport_end_col',
            'emotionElementViewport.visible AS __emotionElementViewport_visible',
        ];

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getCustomFacetFields(): array
    {
        return [
            'customFacet.id as __customFacet_id',
            'customFacet.unique_key as __customFacet_unique_key',
            'customFacet.active as __customFacet_active',
            'customFacet.position as __customFacet_position',
            'customFacet.name as __customFacet_name',
            'customFacet.facet as __customFacet_facet',
        ];
    }

    /**
     * @return string[]
     */
    public function getCustomSortingFields(): array
    {
        return [
            'customSorting.id as __customSorting_id',
            'customSorting.label as __customSorting_label',
            'customSorting.active as __customSorting_active',
            'customSorting.display_in_categories as __customSorting_display_in_categories',
            'customSorting.position as __customSorting_position',
            'customSorting.sortings as __customSorting_sortings',
        ];
    }

    /**
     * Returns an array with all required blog fields.
     * Requires that the s_blog table is included with table alias 'blog'
     *
     * @return array
     */
    public function getBlogFields(): array
    {
        $fields = [
            'blog.id AS __blog_id',
            'blog.title AS __blog_title',
            'blog.author_id AS __blog_author_id',
            'blog.active AS __blog_active',
            'blog.short_description AS __blog_short_description',
            'blog.description AS __blog_description',
            'blog.views AS __blog_views',
            'blog.display_date AS __blog_display_date',
            'blog.category_id AS __blog_category_id',
            'blog.template AS __blog_template',
            'blog.meta_keywords AS __blog_meta_keywords',
            'blog.meta_description AS __blog_meta_description',
            'blog.meta_title AS __blog_meta_title',
        ];

        $fields = array_merge(
            $fields,
            $this->getTableFields('s_blog_attributes', 'blogAttribute')
        );

        return $fields;
    }

    /**
     * Returns an array with all required blog fields.
     * Requires that the s_blog table is included with table alias 'blog'
     *
     * @return array
     */
    public function getBlogCommentFields(): array
    {
        $fields = [
            'blogComment.id AS __blogComment_id',
            'blogComment.blog_id AS __blogComment_blog_id',
            'blogComment.name AS __blogComment_name',
            'blogComment.headline AS __blogComment_headline',
            'blogComment.comment AS __blogComment_comment',
            'blogComment.creation_date AS __blogComment_creation_date',
            'blogComment.active AS __blogComment_active',
            'blogComment.email AS __blogComment_email',
            'blogComment.points AS __blogComment_points',
        ];

        return $fields;
    }

    /**
     * Joins the translation table and selects the objectdata for the provided join conditions
     *
     * @param string                                      $fromPart        Table which uses as from part
     * @param string                                      $joinCondition   Join condition for the objectkey column
     * @param string                                      $translationType Type of the translation
     * @param string                                      $selectName      Name of the additional selection
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addTranslation(
        $fromPart,
        $translationType,
        QueryBuilder $query,
        TranslationContext $context,
        $joinCondition = null,
        $selectName = null
    ): void {
        if ($context->isDefaultShop()) {
            return;
        }

        if ($joinCondition === null) {
            $joinCondition = $fromPart . '.id';
        }
        if ($selectName === null) {
            $selectName = '__' . $fromPart . '_translation';
        }

        $this->addTranslationWithSuffix(
            $fromPart,
            $joinCondition,
            $translationType,
            $selectName,
            $query,
            $context->getShopId()
        );

        if ($context->getFallbackId() !== $context->getShopId()) {
            $this->addTranslationWithSuffix(
                $fromPart,
                $joinCondition,
                $translationType,
                $selectName,
                $query,
                $context->getFallbackId(),
                'fallback'
            );
        }
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addCountryTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('country', 'config_countries', $query, $context, 1);
        $this->addTranslation('countryAttribute', 's_core_countries_attributes', $query, $context, 'country.id');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addCountryStateTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('countryState', 'config_country_states', $query, $context, 1);
        $this->addTranslation('countryStateAttribute', 's_core_countries_states_attributes', $query, $context, 'countryStateAttribute.stateID');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addMediaTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('mediaAttribute', 's_media_attributes', $query, $context, 'mediaAttribute.mediaID');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addUnitTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('unit', 'config_units', $query, $context, 1);
    }

    /**
     * @param QueryBuilder                                $queryBuilder
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addEsdTranslation(QueryBuilder $queryBuilder, TranslationContext $context): void
    {
        $this->addTranslation('esdAttribute', 's_articles_esd_attributes', $queryBuilder, $context, 'esd.id');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addConfiguratorGroupTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('configuratorGroup', 'configuratorgroup', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addConfiguratorOptionTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('configuratorOption', 'configuratoroption', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addDownloadTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('downloadAttribute', 's_articles_downloads_attributes', $query, $context, 'download.id');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addLinkTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('linkAttribute', 's_articles_information_attributes', $query, $context, 'link.id');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addProductTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('product', 'article', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addVariantTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('variant', 'variant', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addPriceTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('priceAttribute', 's_articles_prices_attributes', $query, $context, 'price.id');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addManufacturerTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('manufacturer', 'supplier', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addImageTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('image', 'articleimage', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addPropertySetTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('propertySet', 'propertygroup', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addPropertyGroupTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('propertyGroup', 'propertyoption', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addPropertyOptionTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('propertyOption', 'propertyvalue', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addProductStreamTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('stream', 'productStream', $query, $context);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addCustomerTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('customerAttribute', 's_user_attributes', $query, $context, 'customerAttribute.id');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addAddressTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('addressAttribute', 's_user_addresses_attributes', $query, $context, 'addressAttribute.id');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addEmotionElementTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('emotionElementValue', 'emotionElement', $query, $context, 'emotionElementValue.elementID');
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addCustomSortingTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('customSorting', 'custom_sorting', $query, $context, 1);
    }

    /**
     * @param QueryBuilder                                $query
     * @param \Shopware\Context\Struct\TranslationContext $context
     */
    public function addCustomFacetTranslation($query, $context): void
    {
        $this->addTranslation('customFacet', 'custom_facet', $query, $context, 1);
    }

    public function addDeliveryTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('shippingMethod', 'config_dispatch', $query, $context, 1);
        $this->addTranslation('shippingMethodAttribute', 's_premium_dispatch_attributes', $query, $context, 'shippingMethod.id');
    }

    public function addPaymentTranslation(QueryBuilder $query, TranslationContext $context): void
    {
        $this->addTranslation('paymentMethod', 'config_payment', $query, $context, 1);
        $this->addTranslation('paymentMethodAttribute', 's_core_paymentmeans_attributes', $query, $context, 'paymentMethod.id');
    }

    public function getSeoUrlFields(): array
    {
        return [
            'seoUrl.id as __seoUrl_id',
            'seoUrl.foreign_key as __seoUrl_foreign_key',
            'seoUrl.name as __seoUrl_name',
            'seoUrl.shop_id as __seoUrl_shop_id',
            'seoUrl.path_info as __seoUrl_path_info',
            'seoUrl.seo_path_info as __seoUrl_seo_path_info',
            'seoUrl.created_at as __seoUrl_created_at',
            'seoUrl.is_canonical as __seoUrl_is_canonical',
            'seoUrl.seo_hash as __seoUrl_seo_hash',
        ];
    }

    /**
     * @param string       $fromPart        Table which uses as from part
     * @param string       $joinCondition   Join condition for the objectkey column
     * @param string       $translationType Type of the translation
     * @param string       $selectName      Name of the additional selection
     * @param QueryBuilder $query
     * @param int          $shopId
     * @param string       $suffix
     */
    private function addTranslationWithSuffix(
        $fromPart,
        $joinCondition,
        $translationType,
        $selectName,
        QueryBuilder $query,
        $shopId,
        $suffix = ''
    ): void {
        $selectSuffix = !empty($suffix) ? '_' . strtolower($suffix) : '';

        $translationTable = uniqid('translation', false) . $suffix . $translationType;

        $selectName .= $selectSuffix;

        $query->leftJoin(
            $fromPart,
            's_core_translations',
            $translationTable,
            $translationTable . '.objecttype = :' . $translationTable . ' AND ' .
            $translationTable . '.objectkey = ' . $joinCondition . ' AND ' .
            $translationTable . '.objectlanguage = :language' . $suffix
        );
        $query->setParameter('language' . $suffix, $shopId);
        $query->setParameter('' . $translationTable, $translationType);
        $query->addSelect($translationTable . '.objectdata as ' . $selectName);
    }
}
