import template from './sw-settings-storefront-configuration.html.twig';
import './sw-settings-storefront-configuration.scss';

/**
 * @deprecated tag:v6.8.0 - Will be @private
 * @sw-package framework
 */
export default {
    template,

    inject: ['feature'],

    props: {
        storefrontSettings: {
            type: Object,
            required: true,
        },
    },
};
