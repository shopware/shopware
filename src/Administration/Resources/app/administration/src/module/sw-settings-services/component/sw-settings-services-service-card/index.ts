/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import { MtPopoverItem, MtModalRoot, MtModal, MtModalAction } from '@shopware-ag/meteor-component-library';
import type { ServiceDescription } from '../../service/shopware-services.service';
import template from './sw-settings-services-service-card.html.twig';
import './sw-settings-services-service-card.scss';
import extractErrorMessage from '../../composables/extract-error';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-service-card',

    template,

    components: {
        MtPopoverItem,
        MtModalAction,
        MtModalRoot,
        MtModal,
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
            isLoading: false,
        };
    },

    computed: {
        icon() {
            if (this.service.icon) {
                return `data:image/png;base64, ${this.service.icon}`;
            }

            const assetFilter = Shopware.Filter.getByName('asset');

            return assetFilter('/administration/administration/static/img/services/extension-icon-placeholder.svg');
        },

        serviceStatus() {
            if (!this.service.active) {
                return 'red';
            }

            return this.service.requested_privileges.length === 0 ? 'green' : 'orange';
        },

        statusText() {
            switch (this.serviceStatus) {
                case 'green':
                    return 'sw-settings-services.service-card.status-active';
                case 'orange':
                    return 'sw-settings-services.service-card.status-awaiting-permissions';
                case 'red':
                default:
                    return 'sw-settings-services.service-card.status-inactive';
            }
        },

        updatedAt() {
            return new Date(this.service.updated_at).toLocaleDateString();
        },

        readableVersion() {
            return this.service.version.split('-')[0];
        },
    },

    methods: {
        openDeactivateModal(toggleFloatingUi: () => void) {
            this.showDeactivateModal = true;
            toggleFloatingUi();
        },

        async setActive(active: boolean, toggleFloatingUi?: () => void) {
            this.isLoading = true;

            try {
                const extensionService = Shopware.Service('shopwareExtensionService');

                if (active) {
                    await extensionService.activateExtension(this.service.name, 'app');
                } else {
                    await extensionService.deactivateExtension(this.service.name, 'app');
                }

                window.location.reload();
            } catch (exception) {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    message: extractErrorMessage(exception),
                });
            } finally {
                this.isLoading = false;
            }

            if (toggleFloatingUi) {
                toggleFloatingUi();
            }
        },
    },
});
