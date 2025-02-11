import template from './sw-first-run-wizard-shopware-base.html.twig';

/**
 * @sw-package fundamentals@after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        filteredAttributes() {
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
