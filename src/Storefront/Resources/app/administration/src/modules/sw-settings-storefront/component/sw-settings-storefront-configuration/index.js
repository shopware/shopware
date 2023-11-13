import template from './sw-settings-storefront-configuration.html.twig';
import './sw-settings-storefront-configuration.scss';

/**
 * @package services-settings
 */
Shopware.Component.register('sw-settings-storefront-configuration', {
    template,

    inject: ['feature'],

    props: {
        storefrontSettings: {
            type: Object,
            required: true,
        },
    },
});
