import template from './sw-text-field.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-text-field and mt-text-field. Autoswitches between the two components.
 */
Component.register('sw-text-field', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        modelValue: {
            type: String,
            required: false,
            default: null,
        },

        value: {
            type: String,
            required: false,
            default: null,
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
                'sw-text-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-text-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-text-field" instead.',
            );

            return false;
        },

        compatValue: {
            get() {
                if (this.value === null || this.value === undefined) {
                    return this.modelValue;
                }

                return this.value;
            },
            set(value: string) {
                this.$emit('update:value', value);
                this.$emit('update:modelValue', value);
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

        handleUpdateModelValue(event: unknown) {
            this.$emit('update:modelValue', event);

            // Emit old event for backwards compatibility
            this.$emit('update:value', event);
        },
    },
});
