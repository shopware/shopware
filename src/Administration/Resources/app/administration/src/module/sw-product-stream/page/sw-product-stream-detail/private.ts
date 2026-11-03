import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-product-stream-detail',
    load: () => import('./src/sw-product-stream-detail'),
    public: {
        props: [
            'productStreamId',
        ],
        blocks: [
            'sw_product_stream_detail',
            'sw_product_stream_detail_header',
            'sw_product_stream_detail_actions',
            'sw_product_stream_detail_actions_abort',
            'sw_product_stream_detail_actions_save',
            'sw_product_stream_detail_actions_save_context_menu',
            'sw_product_stream_detail_actions_save_context_menu_actions',
            'sw_product_stream_detail_actions_duplicate',
            'sw_product_stream_detail_language_switch',
            'sw_product_stream_detail_content',
            'sw_product_stream_detail_content_language_info',
            'sw_product_stream_detail_basic_info',
            'sw_product_basic_form_name_field',
            'sw_product_basic_form_description_field',
            'sw_product_basic_form_display_as_group_field',
            'sw_product_stream_detail_filter',
            'sw_product_stream_detail_filter_preview_button',
            'sw_product_stream_detail_filter_tree',
            'sw_prouct_stream_detail_preview_modal',
            'sw_prouct_stream_detail_custom_field_sets',
        ],
        computed: [
            'identifier',
            'productStreamRepository',
            'productStreamFiltersRepository',
            'customFieldSetRepository',
            'tooltipSave',
            'tooltipCancel',
            'isSystemLanguage',
            'nameRequired',
            'showCustomFields',
            'productStreamIndexingEnabled',
            'deprecatedFiltersInUse',
        ],
        methods: [
            'createdComponent',
            'loadCustomFieldSets',
            'loadProductTypes',
            'createProductStream',
            'loadEntityData',
            'loadFilters',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'onDuplicate',
            'onSave',
            'showErrorNotification',
            'saveProductStream',
            'syncProductStreamFilters',
            'onCancel',
            'openModalPreview',
            'closeModalPreview',
            'getProductCustomFields',
            'getCustomFieldLabel',
            'mapCustomFieldType',
            'updateFilterTree',
            'getNoPermissionsTooltip',
        ],
    },
});
