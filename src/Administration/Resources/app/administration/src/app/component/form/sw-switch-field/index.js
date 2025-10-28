import template from './sw-switch-field.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-switch-field and mt-switch. Autoswitches between the two components.
 */
Component.register('sw-switch-field', {
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['update:value'],

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
            return this.value || this.checked;
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
            const listeners = {};

            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                /**
                 * Do not pass "update:value" listeners to the input elements
                 * because the component implements its own listeners for this event types.
                 * The callback methods will emit the corresponding event to the parent.
                 */
                Object.keys(this.$listeners).forEach((key) => {
                    if (
                        ![
                            'update:value',
                        ].includes(key)
                    ) {
                        listeners[key] = this.$listeners[key];
                    }
                });
            }

            return listeners;
        },
    },

    methods: {
        onChangeHandler(value) {
            // For backwards compatibility
            this.$emit('update:value', value);
        },
    },
});
