import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-custom-field-detail',
    load: () => import('./src/sw-custom-field-detail'),
    public: {
        props: [
            'currentCustomField',
            'set',
        ],
        emits: [
            'custom-field-edit-cancel',
            'custom-field-edit-save',
        ],
        blocks: [
            'sw_custom_field_detail',
            'sw_custom_field_detail_modal',
            'sw_custom_field_detail_modal_type',
            'sw_custom_field_detail_modal_technical_name',
            'sw_custom_field_detail_modal_position',
            'sw_custom_field_detail_modal_allow_cart_expose',
            'sw_custom_field_detail_modal_allow_customer_write',
            'sw_custom_field_detail_modal_store_api_aware',
            'sw_custom_field_detail_modal_allow_searchable',
            'sw_custom_field_detail_modal_searchable_banner',
            'sw_custom_field_detail_modal_snippet_info',
            'sw_custom_field_detail_modal_render_component',
            'sw_custom_field_detail_modal_footer',
            'sw_custom_field_detail_modal_footer_cancel',
            'sw_custom_field_detail_modal_footer_save',
        ],
        computed: [
            'locales',
            'canSave',
            'renderComponentName',
            'modalTitle',
            'labelSaveButton',
            'isProductCustomField',
            'ruleConditionRepository',
            'customFieldTypeOptions',
            'genericConfigKeys',
        ],
        methods: [
            'createdComponent',
            'onCancel',
            'onSave',
            'createNameNotUniqueNotification',
            'createEntityTypeRequiredNotification',
            'applyTypeConfiguration',
            'resetTypeConfiguration',
            'getCartExposeTooltipConfig',
        ],
    },
});
