import template from './sw-first-run-wizard-paypal-base.html.twig';

/**
 * @sw-package fundamentals@after-sales
 *
 * @public
 */
export default {
    template,

    computed: {
        filteredAttributes() {
            const filteredAttributes = {};

            Object.entries(this.$attrs).forEach(([key, value]) => {
                if (key.startsWith('on') && typeof value === 'function') {
                    filteredAttributes[key] = value;
                }
            });

            return filteredAttributes;
        },
    },
};
