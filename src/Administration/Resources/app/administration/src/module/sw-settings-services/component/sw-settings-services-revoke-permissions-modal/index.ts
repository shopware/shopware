import { MtModalRoot, MtModalTrigger, MtModalAction, MtModalClose, MtModal } from '@shopware-ag/meteor-component-library';
import template from './sw-settings-services-revoke-permissions-modal.html.twig';
import './sw-settings-services-revoke-permissions-modal.scss';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import extractErrorMessage from '../../composables/extract-error';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-revoke-permissions-modal',
    template,

    components: {
        MtModalRoot,
        MtModal,
        MtModalAction,
        MtModalTrigger,
        MtModalClose,
    },

    emits: ['service-permissions-revoked'],

    data() {
        return {
            isLoading: false,
        };
    },

    methods: {
        async revokePermissions(close: () => void) {
            try {
                const shopwareServicesStore = useShopwareServicesStore();

                this.isLoading = true;
                shopwareServicesStore.config = await Shopware.Service('shopwareServicesService').revokePermissions();

                this.$emit('service-permissions-revoked');
            } catch (exception) {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: extractErrorMessage(exception),
                });
            } finally {
                this.isLoading = false;
                close();
            }
        },
    },
});
