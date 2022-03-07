import template from './sw-settings-storefront-configuration.html.twig';
import './sw-settings-storefront-configuration.scss';

Shopware.Component.register('sw-settings-storefront-configuration', {
    template,

    props: {
        storefrontSettings: {
            type: Object,
            required: true,
        },
    },
});
