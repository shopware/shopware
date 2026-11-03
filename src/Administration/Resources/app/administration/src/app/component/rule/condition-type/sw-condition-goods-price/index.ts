import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-condition-base,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-condition-goods-price',
    extends: 'sw-condition-base',
    load: () => import('./src/sw-condition-goods-price'),
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
            'sw_condition_value_content',
            'sw_condition_goods_price_field_operator',
            'sw_condition_goods_price_field_amount',
            'sw_condition_goods_price_filter_button',
            'sw_condition_goods_price_condition_modal',
            'sw_condition_base',
            'sw_condition_base_content',
            'sw_condition_base_field_condition',
            'sw_condition_value_before_content',
            'sw_condition_base_field_actions',
            'sw_condition_base_field_create_before_action',
            'sw_condition_base_field_create_after_action',
            'sw_condition_base_field_delete_action',
            'sw_condition_base_error',
        ],
        computed: [
            'operators',
            'amount',
            'conditionTypeIdentifier',
            'conditionClasses',
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
            'onCreateBefore',
            'onCreateAfter',
            'onDeleteCondition',
            'ensureValueExist',
        ],
    },
});
