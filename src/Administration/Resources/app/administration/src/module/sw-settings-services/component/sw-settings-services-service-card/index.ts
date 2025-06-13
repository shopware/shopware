/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import { MtPopoverItem, MtModalAction } from '@shopware-ag/meteor-component-library';
import type { ServiceDescription } from '../../service/shopware-services.service';
import template from './sw-settings-services-service-card.html.twig';
import './sw-settings-services-service-card.scss';
import extractErrorMessage from '../../composables/extract-error'

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

            return this.service.requested_privileges.length === 0 ? 'green' : 'orange';
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

        updatedAt() {
            return (new Date(this.service.updated_at)).toLocaleDateString();
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
                    await servicesService.deactivateService(this.service.name);
                } else {
                    await servicesService.activateService(this.service.name);
                }

                this.service.active = active
            } catch (exception) {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    message: extractErrorMessage(exception),
                });
            }

            if(toggleFloatingUi) {
                toggleFloatingUi();
            }
        },
    },
})