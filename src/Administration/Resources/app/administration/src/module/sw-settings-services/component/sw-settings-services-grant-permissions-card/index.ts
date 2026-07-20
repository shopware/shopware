/**
 * @sw-package framework
 */
import template from './sw-settings-services-grant-permissions-card.html.twig';
import './sw-settings-services-grant-permissions-card.scss';
import { grantPermissions } from '../../composables/permissions';
import extractErrorMessage from '../../composables/extract-error';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-grant-permissions-card',
    template,

    emits: ['service-permissions-granted'],

    props: {
        docsLink: {
            type: String,
            required: true,
        },
    },

    data() {
        const assetFilter = Shopware.Filter.getByName('asset');

        return {
            grantPermissionsCardBackground: assetFilter(
                '/administration/administration/static/img/services/grant-permissions-background.svg',
            ),
            isLoading: false,
        };
    },

    methods: {
        async grantPermissions() {
            try {
                this.isLoading = true;

                await grantPermissions();
            } catch (exception) {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: extractErrorMessage(exception),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
