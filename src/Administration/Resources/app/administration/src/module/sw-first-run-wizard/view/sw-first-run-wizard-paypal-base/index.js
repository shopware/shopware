import template from './sw-first-run-wizard-paypal-base.html.twig';

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    computed: {
        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },

        filteredAttributes() {
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return {};
            }

            const filteredAttributes = {};

            Object.entries(this.$attrs).forEach(
                ([
                    key,
                    value,
                ]) => {
                    if (key.startsWith('on') && typeof value === 'function') {
                        filteredAttributes[key] = value;
                    }
                },
            );

            return filteredAttributes;
        },
    },
};
