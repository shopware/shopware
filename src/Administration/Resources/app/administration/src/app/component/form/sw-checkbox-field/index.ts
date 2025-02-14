import template from './sw-checkbox-field.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-checkbox-field and mt-checkbox-field. Autoswitches between the two components.
 */
Component.register('sw-checkbox-field', {
    template,

    props: {
        modelValue: {
            type: String,
            required: false,
            default: null,
        },

        value: {
            type: Boolean,
            required: false,
            default: null,
        },
    },

    computed: {
        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('ENABLE_METEOR_COMPONENTS')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-checkbox-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-checkbox-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-checkbox" instead.',
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
    },

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

            return this.$slots;
        },

        handleUpdateChecked(event: unknown) {
            this.$emit('update:checked', event);

            // Emit old event for backwards compatibility
            this.$emit('update:value', event);
        },
    },
});
