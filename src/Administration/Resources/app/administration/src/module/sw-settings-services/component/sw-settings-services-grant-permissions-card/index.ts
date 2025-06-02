/**
 * @sw-package framework
 */
import template from './sw-settings-services-grant-permissions-card.html.twig';
import './sw-settings-services-grant-permissions-card.scss'
// eslint-disable-next-line
import grantPermissionsCardBackground from '../sw-settings-services-grant-permissions-modal/assets/grant-permissions-background.svg?no-inline';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-grant-permissions-card',
    template,

    props: {
        tosLink: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            grantPermissionsCardBackground,
        };
    },

    methods: {
        grantPermissions() {
            console.log('granting permissions...');
        },
    },
})
