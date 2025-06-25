import {
    MtModalTrigger,
    MtModalAction,
    MtModalClose,
} from '@shopware-ag/meteor-component-library';
import template from './sw-settings-services-deactivate-modal.html.twig';
import './sw-settings-services-deactivate-modal.scss';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import extractError from '../../composables/extract-error';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-deactivate-modal',
    template,

    components: {
        MtModalAction,
        MtModalTrigger,
        MtModalClose,
    },

    props: {
        feedbackLink: {
            type: String,
        },
    },

    methods: {
        async disableServices(done: () => void) {
            try {
                const  shopwareServicesService = Shopware.Service('shopwareServicesService');
                const shopwareServicesStore = useShopwareServicesStore();

                shopwareServicesStore.config = await shopwareServicesService.disableAllServices();

                this.$emit('service-disabled')
            } catch (exceptionResponse) {
                Shopware.Store.get('notification').createNotification({
                    title: this.$t('global.default.error'),
                    variant: 'critical',
                    message: extractError(exceptionResponse),
                    autoClose: false,
                });
            }

            done();
        },
    },
})