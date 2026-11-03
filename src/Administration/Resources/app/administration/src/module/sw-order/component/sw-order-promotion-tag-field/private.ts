import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-tagged-field,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-order-promotion-tag-field',
    extends: 'sw-tagged-field',
    load: () => import('./src/sw-order-promotion-tag-field'),
    public: {
        props: [
            'currency',
            'disabled',
            'value',
            'placeholder',
            'addOnKey',
        ],
        emits: [
            'update:value',
            'on-remove-code',
        ],
        slots: [
            'label',
            'hint',
        ],
        blocks: [
            'sw_tagged_field',
            'sw_order_promotion_tag_field',
            'sw_tagged_field_inner',
            'sw_order_promotion_tag_field_tag_list',
            'sw_order_promotion_tag_field_tag_item',
            'sw_order_promotion_tag_field_item_input',
            'sw_order_promotion_tag_field_input',
            'sw_tagged_field_item_input',
            'sw_tagged_field_input',
        ],
        computed: [
            'taggedFieldListClasses',
            'taggedFieldInputClasses',
            'hasValues',
            'taggedFieldClasses',
        ],
        methods: [
            'performAddTag',
            'dismissTag',
            'setFocus',
            'getPromotionCodeDescription',
            'dismissLastTag',
            'noTriggerKey',
        ],
    },
});
