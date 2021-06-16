import template from './sw-step-item.html.twig';
import './sw-step-item.scss';

const { Component } = Shopware;
/**
 * @public
 * @description Renders a step and must be used in the slot of the sw-step-display component.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-step-item disabledIcon="small-default-checkmark-line-medium">
 *     Finish
 * </sw-step-item>
 */
Component.register('sw-step-item', {
    template,

    props: {
        disabledIcon: {
            type: String,
            default: 'small-default-circle-medium',
            required: false,
        },
    },

    data() {
        return {
            variant: 'disabled',
            active: false,
        };
    },

    computed: {
        modifierClasses() {
            return [
                `sw-step-item--${this.variant}`,
                {
                    'sw-step-item--active': this.active,
                },
            ];
        },

        icon() {
            const iconConfig = {
                disabled: this.disabledIcon,
                info: 'small-default-circle-medium',
                error: 'small-default-x-line-medium',
                success: 'small-default-checkmark-line-medium',
            };

            return iconConfig[this.variant];
        },
    },

    methods: {
        setActive(active) {
            this.active = active;
        },

        setVariant(variant) {
            if (!['disabled', 'info', 'error', 'success'].includes(variant)) {
                return;
            }

            this.variant = variant;
        },
    },
});
