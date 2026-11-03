import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-condition-base,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-product-stream-filter',
    extends: 'sw-condition-base',
    load: () => import('./src/sw-product-stream-filter'),
    public: {
        props: [
            'condition',
            'disabled',
        ],
        emits: [
            'create-before',
            'create-after',
            'condition-delete',
        ],
        blocks: [
            'sw_condition_base_field_condition',
            'sw_product_stream_filter',
            'sw_product_stream_filter_container',
            'sw_product_stream_filter_selects',
            'sw_product_stream_filter_fields',
            'sw_product_stream_filter_field_select',
            'sw_condition_value_content',
            'sw_product_stream_filter_value',
            'sw_condition_base_field_actions',
            'sw_product_stream_filter_field_actions',
            'sw_product_stream_filter_field_create_before_action',
            'sw_product_stream_filter_field_create_after_action',
            'sw_product_stream_filter_field_delete_action',
            'sw_condition_base_error',
            'sw_product_stream_filter_error',
            'sw_condition_base',
            'sw_condition_base_content',
            'sw_condition_value_before_content',
            'sw_condition_base_field_create_before_action',
            'sw_condition_base_field_create_after_action',
            'sw_condition_base_field_delete_action',
        ],
        computed: [
            'conditionClasses',
            'actualCondition',
            'fields',
            'fieldDefinitions',
            'lastField',
            'conditionTypeIdentifier',
            'errorTree',
            'fieldErrors',
            'typeError',
            'errorCount',
            'listedErrors',
            'currentError',
            'hasError',
            'valueErrorPath',
            'value',
            'isDisabled',
            'hasNoComponent',
            'operator',
            'isEmpty',
        ],
        methods: [
            'updateFields',
            'handleWrapForTypeNull',
            'changeBooleanValue',
            'changeEmptyValue',
            'changeType',
            'applyPercentageDefaultValue',
            'wrapInNot',
            'unwrapNot',
            'copyParameters',
            'getNoPermissionsTooltip',
            'isCustomField',
            'onCreateBefore',
            'onCreateAfter',
            'onDeleteCondition',
            'ensureValueExist',
        ],
    },
});
