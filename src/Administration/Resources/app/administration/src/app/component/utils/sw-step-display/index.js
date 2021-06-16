import template from './sw-step-display.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description This step display component need flow-items inside it's slot to work.
 * To control the current position use the `itemIndex` property (zero-based index).
 * To change the variant of the current position you can use the `itemVariant` property.
 * To load specific variants for multiple items you can use the `initialItemVariants` property
 * with an array of variants. Possible variants are 'disabled', 'info', 'error' and 'success'.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-step-display :itemIndex="flowChartItemIndex"
 *                            :itemVariant="flowChartItemVariant"
 *                            :initialItemVariants="flowChartInitialItemVariants">
 *   <sw-step-item>
 *      Check
 *   </sw-step-item>
 *   <sw-step-item>
 *      Read
 *   </sw-step-item>
 *   <sw-step-item disabledIcon="small-default-checkmark-line-medium">
 *      Finish
 *   </sw-step-item>
 * </sw-step-display>
 */
Component.register('sw-step-display', {
    template,

    props: {
        itemIndex: {
            type: Number,
            required: true,
        },
        itemVariant: {
            type: String,
            required: true,
        },
        initialItemVariants: {
            type: Array,
            default() {
                return [];
            },
            required: false,
        },
    },

    data() {
        return {
            items: [],
        };
    },

    watch: {
        itemIndex(newIndex, oldIndex) {
            this.setItemActive(oldIndex, false);
            this.setItemActive(newIndex, true);
            this.setVariantForCurrentItem(this.itemVariant);
        },
        itemVariant(newVariant) {
            this.setVariantForCurrentItem(newVariant);
        },
        initialItemVariants: {
            deep: true,
            handler(newItemVariants) {
                this.setItemVariants(newItemVariants);
            },
        },
    },

    mounted() {
        // read child step items
        this.$children.forEach((child) => {
            if (child.$options._componentTag === 'sw-step-item') {
                this.items.push(child);
            }
        });

        this.setItemVariants(this.initialItemVariants);
        this.setItemActive(this.itemIndex, true);
        this.setVariantForCurrentItem(this.itemVariant);
    },

    methods: {
        setItemVariants(itemVariants) {
            const max = Math.min(this.items.length, itemVariants.length);
            for (let i = 0; i < max; i += 1) {
                this.items[i].setVariant(itemVariants[i]);
            }
        },

        setVariantForCurrentItem(variant) {
            if (this.itemIndex >= this.items.length) {
                return;
            }

            this.items[this.itemIndex].setVariant(variant);
        },

        setItemActive(index, active) {
            if (index >= this.items.length) {
                return;
            }

            this.items[index].setActive(active);
        },
    },
});
