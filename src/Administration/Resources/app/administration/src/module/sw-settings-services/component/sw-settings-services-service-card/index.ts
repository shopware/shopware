/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import type { AxiosError } from 'axios';
import { MtPopoverItem, MtModalAction } from '@shopware-ag/meteor-component-library';
import type { ServiceDescription } from '../../service/shopware-services.service';
import template from './sw-settings-services-service-card.html.twig';
import './sw-settings-services-service-card.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-service-card',

    template,

    components: {
        MtPopoverItem,
        MtModalAction,
    },

    props: {
        service: {
            required: true,
            type: Object as PropType<ServiceDescription>,
        },
    },

    data() {
        return {
            showDeactivateModal: false,
        };
    },

    computed: {
        serviceStatus() {
            if (!this.service.active) {
                return 'red';
            }

            return this.service.requestedPermissions.length === 0 ? 'green' : 'orange';
        },

        statusText() {
            switch (this.serviceStatus) {
                case 'green': return 'sw-settings-services.service-card.status-active';
                case 'orange': return 'sw-settings-services.service-card.status-awaiting-permissions';
                case 'red':
                default:
                    return 'sw-settings-services.service-card.status-inactive';
            }
        },
    },

    methods: {
        openDeactivateModal(toggleFloatingUi: () => void) {
            this.showDeactivateModal = true;
            toggleFloatingUi();
        },

        async setActive(active: boolean, toggleFloatingUi?: () => void) {
            try {
                const servicesService = Shopware.Service('shopwareServicesService');

                if (active) {
                    await servicesService.deactivateService(this.service.technicalName);
                } else {
                    await servicesService.activateService(this.service.technicalName);
                }

                this.service.active = active
            } catch (exception) {
                let message = '';

                if (exception instanceof Error) {
                    message = exception.message;
                }

                if (this.isAxiosError(exception)) {
                    if (exception.response?.data.errors[0]?.detail) {
                        message = exception.response?.data.errors[0]?.detail;
                    }
                }

                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message,
                });
            }

            if(toggleFloatingUi) {
                toggleFloatingUi();
            }
        },

        isAxiosError(exception: unknown): exception is AxiosError<{ errors: ShopwareHttpError[] }> {
            return typeof exception === 'object' && exception !== null && exception.name === 'AxiosError';
        },
    },
})