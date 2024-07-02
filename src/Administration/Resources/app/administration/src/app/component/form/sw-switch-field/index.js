import template from './sw-switch-field.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-switch-field and mt-switch. Autoswitches between the two components.
 */
Component.register('sw-switch-field', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        value: {
            type: Boolean,
            required: false,
        },

        checked: {
            type: Boolean,
            required: false,
        },
    },

    computed: {
        checkedValue() {
            if (typeof this.checked === 'boolean') {
                return this.checked;
            }

            return this.value;
        },

        useMeteorComponent() {
            // Use new meteor component in major
            if (Shopware.Feature.isActive('v6.7.0.0')) {
                return true;
            }

            // Throw warning when deprecated component is used
            Shopware.Utils.debug.warn(
                'sw-switch-field',
                // eslint-disable-next-line max-len
                'The old usage of "sw-switch-field" is deprecated and will be removed in v6.7.0.0. Please use "mt-switch" instead.',
            );

            return false;
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
        onChangeHandler(value) {
            // For backwards compatibility
            this.$emit('update:value', value);
        },
    },
});
