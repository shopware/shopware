import template from './sw-textarea-field.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-textarea-field and mt-textarea. Autoswitches between the two components.
 */
Component.register('sw-textarea-field', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        placeholder: {
            type: String,
            required: false,
            default: undefined,
        },

        value: {
            type: String,
            required: false,
            default: undefined,
        },

        modelValue: {
            type: String,
            required: false,
            default: undefined,
        },
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-textarea-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-textarea-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-textarea" instead.',
            );

            return false;
        },

        realValue: {
            get() {
                return this.modelValue || this.value;
            },
            set(value: string) {
                if (this.useMeteorComponent) {
                    this.$emit('update:value', value);
                } else {
                    this.$emit('update:modelValue', value);
                }
            },
        },

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return {
                    ...this.$slots,
                    ...this.$scopedSlots,
                };
            }

            return this.$slots;
        },
    },
});
