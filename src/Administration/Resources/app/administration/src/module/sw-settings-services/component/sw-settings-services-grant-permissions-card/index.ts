/**
 * @sw-package framework
 */
import template from './sw-settings-services-grant-permissions-card.html.twig';
import './sw-settings-services-grant-permissions-card.scss'
// eslint-disable-next-line
import grantPermissionsCardBackground from '../sw-settings-services-grant-permissions-modal/assets/grant-permissions-background.svg?no-inline';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import extractErrorMessage from '../../composables/extract-error';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-grant-permissions-card',
    template,

    emits: ['service-permissions-granted'],

    props: {
        tosLink: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            grantPermissionsCardBackground,
            isLoading: false,
        };
    },

    methods: {
        async grantPermissions() {
            try {
                const shopwareServiceStore = useShopwareServicesStore();
                const currentRevision = shopwareServiceStore.currentRevision?.revision;

                if (!currentRevision) {
                    throw new Error('No revision available');
                }

                this.isLoading = true;

                shopwareServiceStore.config = await Shopware.Service('shopwareServicesService')
                    .acceptRevision(currentRevision);

                this.$emit('service-permissions-granted');
            } catch(exception) {
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
})
