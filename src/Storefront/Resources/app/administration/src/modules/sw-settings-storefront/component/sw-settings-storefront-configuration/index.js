import template from './sw-settings-storefront-configuration.html.twig';
import './sw-settings-storefront-configuration.scss';

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement.
 * @sw-package framework
 */
export default {
    template,

    deprecated: {
        version: 'v6.8.0.0',
        comment: 'Will be removed without replacement.',
    },

    inject: ['feature'],

    props: {
        storefrontSettings: {
            type: Object,
            required: true,
        },
    },
};
